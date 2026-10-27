<?php

namespace App\Livewire;

use App\Enums\IllustrationCategory;
use App\Livewire\Concerns\Notifies;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\MusicTrack;
use App\Services\Invitation\DecorationResolver;
use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\Ornaments;
use App\Services\Invitation\SectionRegistry;
use App\Services\Invitation\ThemeResolver;
use App\Services\MusicService;
use App\Services\Template\TemplateDraft;
use App\Services\Template\TemplateSchema;
use App\Services\Template\TemplateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Admin template builder (rules.md section 51): section structure, layouts, backgrounds,
 * theme, default and sample content, default music, with a live preview of the unsaved draft.
 * Changes stay in a TemplateDraft until the admin saves them.
 */
class TemplateBuilder extends Component
{
    use Notifies;

    #[Locked]
    public string $templateUlid = '';

    /**
     * Ordered sections. "key" only identifies an item inside the builder; it is never stored.
     *
     * @var array<int, array{key: string, type: string, variant: string, background: string, ornaments: array<int, array<string, mixed>>, desktop_ornaments: array<int, array<string, mixed>>}>
     */
    #[Locked]
    public array $sections = [];

    #[Locked]
    public ?string $selected = null;

    public string $panel = 'section';

    public string $content = 'default_configuration';

    /** @var array<string, string> */
    public array $theme = [];

    public ?string $preset = null;

    #[Locked]
    public string $design = DesignRegistry::DEFAULT;

    /** @var array<string, string> Resolved decoration; changed through setDecoration() only. */
    #[Locked]
    public array $decoration = [];

    public bool $ornamentPicker = false;

    /** Which ornament list of the selected section is edited: "ornaments", or the cover's "desktop_ornaments". */
    public string $ornamentTarget = 'ornaments';

    public bool $hasChanges = false;

    /** @var array{track: ?string, start_at: int, loop: bool} Default music; the song changes through chooseMusic() only. */
    public array $music = [];

    public function mount(InvitationTemplate $template): void
    {
        Gate::authorize('templates.manage');

        $this->templateUlid = $template->ulid;
        $this->loadDraft();
    }

    public function select(string $key): void
    {
        $index = $this->sectionIndex($key);
        $this->selected = $key;
        $this->panel = 'section';
        $this->ornamentTarget = 'ornaments';

        // The preview scrolls to the section (preview sections are anchored by position).
        $this->dispatch('builder-section-selected', anchor: 'preview-'.$index);
    }

    public function addSection(string $type, SectionRegistry $registry): void
    {
        if (! $registry->has($type)) {
            return;
        }

        $key = $this->newKey();
        $this->sections[] = ['key' => $key, 'type' => $type, 'variant' => $registry->resolveVariant($type, null), 'background' => 'default', 'ornaments' => [], 'desktop_ornaments' => []];
        $this->selected = $key;
        $this->panel = 'section';
        $this->persistSections();
    }

    public function duplicateSection(string $key): void
    {
        $index = $this->sectionIndex($key);
        $copy = [...$this->sections[$index], 'key' => $this->newKey()];

        array_splice($this->sections, $index + 1, 0, [$copy]);
        $this->selected = $copy['key'];
        $this->persistSections();
    }

    public function removeSection(string $key): void
    {
        if (count($this->sections) <= 1) {
            $this->notify('warning', __('A template needs at least one section.'));

            return;
        }

        $index = $this->sectionIndex($key);
        array_splice($this->sections, $index, 1);

        if ($this->selected === $key) {
            $this->selected = $this->sections[max(0, $index - 1)]['key'];
        }

        $this->persistSections();
    }

    /**
     * wire:sort handler: receives the dragged item key and its new zero-based position.
     */
    public function reorder(string $key, int $position): void
    {
        $moved = array_splice($this->sections, $this->sectionIndex($key), 1);
        array_splice($this->sections, max(0, min($position, count($this->sections))), 0, $moved);

        $this->persistSections();
    }

    public function setVariant(string $key, string $variant, SectionRegistry $registry): void
    {
        $index = $this->sectionIndex($key);

        if (in_array($variant, $registry->variants($this->sections[$index]['type']), true)) {
            $this->sections[$index]['variant'] = $variant;
            $this->persistSections();
        }
    }

    public function setBackground(string $key, string $background): void
    {
        $index = $this->sectionIndex($key);

        if (in_array($background, TemplateSchema::BACKGROUNDS, true)) {
            $this->sections[$index]['background'] = $background;
            $this->persistSections();
        }
    }

    public function updatedContent(string $content): void
    {
        if (! in_array($content, TemplateSectionContent::GROUPS, true)) {
            $this->content = 'default_configuration';
        }

        $this->dispatch('template-preview-refresh');
    }

    public function updatedTheme(ThemeResolver $themes): void
    {
        $this->validate($themes->rules());
        $this->persistTheme();
    }

    public function applyPreset(string $preset, ThemeResolver $themes): void
    {
        if (! isset($themes->presets()[$preset])) {
            return;
        }

        $this->preset = $preset;
        $this->theme = $themes->resolve(['preset' => $preset]);
        $this->persistTheme();
    }

    public function setDesign(string $design, DesignRegistry $designs): void
    {
        if (! $designs->has($design)) {
            return;
        }

        $this->design = $design;
        $this->persist(fn (array $definition): array => [...$definition, 'design' => $design]);
    }

    /**
     * Change one decoration value, e.g. the entrance animation or its speed.
     */
    public function setDecoration(string $key, string $value, DecorationResolver $decorations): void
    {
        if (! $decorations->isValid($key, $value)) {
            return;
        }

        $this->decoration[$key] = $value;
        $this->persist(fn (array $definition): array => [...$definition, 'decoration' => [...($definition['decoration'] ?? []), $key => $value]]);
    }

    /**
     * Add a library ornament to the selected section. Only active illustrations of the "ornament"
     * category can be used, so no arbitrary image URL ends up on invitations.
     */
    public function addOrnament(string $illustrationUlid): void
    {
        $index = $this->selectedIndex();
        $ornaments = $this->sections[$index][$this->ornamentList()] ?? [];

        if (count($ornaments) >= config('invitation.ornament_options.max_per_section')) {
            $this->notify('warning', __('A section can have up to :max ornaments.', ['max' => config('invitation.ornament_options.max_per_section')]));

            return;
        }

        $illustration = Illustration::query()->active()->where('category', IllustrationCategory::Ornament)->where('ulid', $illustrationUlid)->firstOrFail();

        // Alternate sides, so a mirrored pair (left + right) is two clicks away.
        $right = count($ornaments) % 2 === 1;
        $ornaments[] = [
            ...config('invitation.ornament_options.defaults'),
            'image' => $illustration->url(),
            'x' => $right ? 100 : 0,
            'anchor' => $right ? 'bottom-right' : 'bottom-left',
            'flip' => $right,
        ];

        $this->ornamentPicker = false;
        $this->setOrnaments($index, $ornaments);
    }

    /**
     * Change one property of an ornament of the selected section; values are cleaned by Ornaments.
     */
    public function updateOrnament(int $ornament, string $key, string $value): void
    {
        $index = $this->selectedIndex();
        $ornaments = $this->sections[$index][$this->ornamentList()] ?? [];
        $editable = ['x', 'y', 'width', 'anchor', 'flip', 'layer', 'enter', 'duration', 'delay', 'motion'];

        if (! isset($ornaments[$ornament]) || ! in_array($key, $editable, true)) {
            return;
        }

        $ornaments[$ornament][$key] = $key === 'flip' ? $value === '1' : $value;
        $this->setOrnaments($index, $ornaments);
    }

    public function duplicateOrnament(int $ornament): void
    {
        $index = $this->selectedIndex();
        $ornaments = $this->sections[$index][$this->ornamentList()] ?? [];

        if (isset($ornaments[$ornament]) && count($ornaments) < config('invitation.ornament_options.max_per_section')) {
            array_splice($ornaments, $ornament + 1, 0, [$ornaments[$ornament]]);
            $this->setOrnaments($index, $ornaments);
        }
    }

    /**
     * Later ornaments are drawn on top; moving changes the stacking order.
     */
    public function moveOrnament(int $ornament, int $direction): void
    {
        $index = $this->selectedIndex();
        $ornaments = $this->sections[$index][$this->ornamentList()] ?? [];
        $target = $ornament + ($direction < 0 ? -1 : 1);

        if (isset($ornaments[$ornament], $ornaments[$target])) {
            [$ornaments[$ornament], $ornaments[$target]] = [$ornaments[$target], $ornaments[$ornament]];
            $this->setOrnaments($index, $ornaments);
        }
    }

    public function removeOrnament(int $ornament): void
    {
        $index = $this->selectedIndex();
        $ornaments = $this->sections[$index][$this->ornamentList()] ?? [];

        if (isset($ornaments[$ornament])) {
            array_splice($ornaments, $ornament, 1);
            $this->setOrnaments($index, $ornaments);
        }
    }

    /**
     * Replace the selected section's ornaments with a copy of another section's.
     */
    public function copyOrnamentsFrom(string $key): void
    {
        $source = $this->sectionIndex($key);
        $this->setOrnaments($this->selectedIndex(), $this->sections[$source][$this->ornamentList()] ?? []);
    }

    /**
     * Default music: played on the template preview and given to new invitations
     * (unless the owner's plan does not include the song).
     */
    public function chooseMusic(string $trackUlid): void
    {
        $track = MusicTrack::query()->active()->where('ulid', $trackUlid)->firstOrFail();

        $this->persistMusic([...$this->storedMusic(), 'track' => $track->ulid]);
    }

    public function removeMusic(): void
    {
        $this->persistMusic([]);
    }

    /**
     * Only the start time and repeat switch are bound to inputs.
     */
    public function updatedMusic(): void
    {
        $stored = $this->storedMusic();

        if ($stored === []) {
            $this->music = $this->musicState([]);

            return;
        }

        $this->persistMusic([...$stored, 'start_at' => (int) ($this->music['start_at'] ?? 0), 'loop' => (bool) ($this->music['loop'] ?? true)]);
    }

    #[On('template-draft-updated')]
    public function draftUpdated(): void
    {
        $this->hasChanges = true;
    }

    public function save(TemplateService $templates, TemplateDraft $draft): void
    {
        Gate::authorize('templates.manage');

        $template = $this->template();
        $templates->updateDefinition($template, $draft->definition($template, Auth::user()), Auth::user());
        $draft->discard($template, Auth::user());

        $this->hasChanges = false;
        $this->notify('success', $template->isPublished()
            ? __('Template saved. New invitations use this version; existing invitations are not affected.')
            : __('Template saved.'));
        $this->dispatch('template-preview-refresh');
    }

    public function discard(TemplateDraft $draft): void
    {
        Gate::authorize('templates.manage');

        $draft->discard($this->template(), Auth::user());
        $this->loadDraft();

        $this->notify('info', __('Unsaved changes discarded.'));
        $this->dispatch('template-preview-refresh');
    }

    public function render(SectionRegistry $registry, ThemeResolver $themes): View
    {
        $template = $this->template();
        $selected = $this->selected !== null ? collect($this->sections)->firstWhere('key', $this->selected) : null;

        return view('livewire.template-builder', [
            'template' => $template,
            'registry' => $registry,
            'selectedSection' => $selected,
            'sharedCount' => $selected ? collect($this->sections)->where('type', $selected['type'])->count() : 0,
            'addableTypes' => collect($registry->types())->mapWithKeys(fn (string $type): array => [$type => $registry->label($type)]),
            'backgrounds' => TemplateSchema::BACKGROUNDS,
            'previewUrl' => route('admin.templates.builder.preview', $template),
            'presets' => array_keys($themes->presets()),
            'fontsByCategory' => collect(config('invitation.fonts'))->map(fn (array $font, string $name): array => [...$font, 'name' => $name])->groupBy('category'),
            'themeOptions' => config('invitation.theme_options'),
            'designs' => app(DesignRegistry::class)->all(),
            'animations' => [
                'none' => ['None', 'fa-solid fa-ban'],
                'fade' => ['Fade', 'fa-solid fa-circle-half-stroke'],
                'fade-up' => ['Fade up', 'fa-solid fa-arrow-up'],
                'zoom' => ['Zoom', 'fa-solid fa-magnifying-glass-plus'],
                'slide' => ['Slide in', 'fa-solid fa-right-left'],
            ],
            'animationSpeeds' => ['slow' => 'Slow', 'normal' => 'Normal', 'fast' => 'Fast'],
            'ornamentIllustrations' => $this->ornamentPicker
                ? Illustration::query()->active()->where('category', IllustrationCategory::Ornament)->ordered()->get()
                : collect(),
            'ornamentAnchors' => ['top-left' => 'Top left', 'top' => 'Top', 'top-right' => 'Top right', 'left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'bottom-left' => 'Bottom left', 'bottom' => 'Bottom', 'bottom-right' => 'Bottom right'],
            'ornamentEnters' => [
                'none' => 'No entrance', 'fade' => 'Fade', 'fade-up' => 'Fade up', 'fade-down' => 'Fade down', 'fade-left' => 'Fade from the right', 'fade-right' => 'Fade from the left',
                'zoom-in' => 'Zoom in', 'zoom-in-up' => 'Zoom in, rising', 'zoom-in-left' => 'Zoom in from the right', 'zoom-in-right' => 'Zoom in from the left', 'zoom-out' => 'Zoom out',
            ],
            'ornamentMotions' => ['none' => 'Still', 'sway' => 'Sway', 'float' => 'Float'],
            'ornamentList' => $this->ornamentList(),
            'hasDesktopPanel' => $this->hasDesktopPanel(),
            'hasDesktopLayouts' => app(DesignRegistry::class)->hasDesktopLayouts($this->design),
            'musicTracks' => $this->panel === 'music' ? app(MusicService::class)->library() : collect(),
            'selectedTrack' => $this->music['track'] ? MusicTrack::query()->where('ulid', $this->music['track'])->first() : null,
            'maxStartAt' => (int) config('invitation.music.max_start_at'),
            // Label and a small text sample of each decoration choice.
            'dividers' => ['line' => ['Line', '———'], 'dots' => ['Dots', '• • •'], 'flourish' => ['Flourish', '~◆~'], 'none' => ['None', '∅']],
            'textures' => ['none' => 'None', 'paper' => 'Paper', 'linen' => 'Linen', 'grain' => 'Grain'],
            'desktopLayouts' => [
                'column' => ['Centered column', 'The invitation stays phone-sized in the middle, on a blurred cover photo.', 'fa-solid fa-mobile-screen'],
                'split' => ['Side panel', 'A cover panel with its own text and ornaments beside the invitation.', 'fa-solid fa-table-columns'],
                'full' => ['Full width', 'Sections stretch across the whole screen.', 'fa-solid fa-display'],
            ],
        ]);
    }

    private function loadDraft(): void
    {
        $template = $this->template();
        $draft = app(TemplateDraft::class);
        $definition = $draft->definition($template, Auth::user());
        $registry = app(SectionRegistry::class);

        // Upgrades older drafts (e.g. the template-wide ornament set) to the current format.
        try {
            $definition = app(TemplateSchema::class)->validate($definition);
        } catch (ValidationException) {
            // Keep the draft as it is; the next change reports what is wrong.
        }

        $this->sections = collect($template->forceFill($definition)->sectionBlueprint())
            ->filter(fn (array $section): bool => $registry->has($section['type']))
            ->map(fn (array $section): array => [
                'key' => $this->newKey(),
                'type' => $section['type'],
                'variant' => $registry->resolveVariant($section['type'], $section['variant']),
                'background' => $section['background'] ?? 'default',
                'ornaments' => app(Ornaments::class)->clean($section['ornaments']),
                'desktop_ornaments' => app(Ornaments::class)->clean($section['desktop_ornaments']),
            ])
            ->values()
            ->all();

        $this->selected = $this->sections[0]['key'] ?? null;
        $this->preset = $definition['theme']['preset'] ?? null;
        $this->design = app(DesignRegistry::class)->resolve($definition['design'] ?? null);
        $this->decoration = app(DecorationResolver::class)->resolve($definition['decoration'] ?? []);
        $this->theme = app(ThemeResolver::class)->resolve($definition['theme'] ?? []);
        $this->music = $this->musicState($definition['music'] ?? []);
        $this->hasChanges = $draft->hasChanges($template, Auth::user());
    }

    private function persistSections(): void
    {
        $sections = array_map(fn (array $section): array => [
            'type' => $section['type'],
            'variant' => $section['variant'],
            'background' => $section['background'],
            'ornaments' => $section['ornaments'] ?? [],
            'desktop_ornaments' => $section['desktop_ornaments'] ?? [],
        ], $this->sections);

        $this->persist(fn (array $definition): array => [...$definition, 'schema' => ['sections' => $sections]]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $ornaments
     */
    private function setOrnaments(int $index, array $ornaments): void
    {
        $this->sections[$index][$this->ornamentList()] = app(Ornaments::class)->clean($ornaments);
        $this->persistSections();
    }

    /**
     * The cover's desktop panel has its own ornaments, for designs with a desktop panel.
     */
    private function ornamentList(): string
    {
        $selected = collect($this->sections)->firstWhere('key', $this->selected);

        return $this->ornamentTarget === 'desktop_ornaments' && ($selected['type'] ?? null) === 'cover' && $this->hasDesktopPanel()
            ? 'desktop_ornaments'
            : 'ornaments';
    }

    private function hasDesktopPanel(): bool
    {
        return in_array('desktop_panel', app(DesignRegistry::class)->features($this->design, $this->decoration), true);
    }

    private function selectedIndex(): int
    {
        abort_if($this->selected === null, 404);

        return $this->sectionIndex($this->selected);
    }

    /**
     * @param  array<string, mixed>  $music
     */
    private function persistMusic(array $music): void
    {
        $this->persist(fn (array $definition): array => [...$definition, 'music' => $music]);
        $this->music = $this->musicState($this->storedMusic());
    }

    /**
     * @return array<string, mixed>
     */
    private function storedMusic(): array
    {
        return app(TemplateDraft::class)->definition($this->template(), Auth::user())['music'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $music
     * @return array{track: ?string, start_at: int, loop: bool}
     */
    private function musicState(array $music): array
    {
        return ['track' => $music['track'] ?? null, 'start_at' => (int) ($music['start_at'] ?? 0), 'loop' => (bool) ($music['loop'] ?? true)];
    }

    private function persistTheme(): void
    {
        $theme = array_filter(['preset' => $this->preset, ...$this->theme], fn (mixed $value): bool => $value !== null);

        $this->persist(fn (array $definition): array => [...$definition, 'theme' => $theme]);
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function persist(callable $change): void
    {
        Gate::authorize('templates.manage');

        app(TemplateDraft::class)->update($this->template(), Auth::user(), $change);

        $this->resetValidation();
        $this->hasChanges = true;
        $this->dispatch('template-preview-refresh');
    }

    private function sectionIndex(string $key): int
    {
        $index = collect($this->sections)->search(fn (array $section): bool => $section['key'] === $key);
        abort_if($index === false, 404);

        return $index;
    }

    private function newKey(): string
    {
        return Str::lower(Str::random(10));
    }

    private function template(): InvitationTemplate
    {
        return InvitationTemplate::query()->where('ulid', $this->templateUlid)->firstOrFail();
    }
}
