<?php

namespace App\Livewire;

use App\Livewire\Concerns\EditsSectionFields;
use App\Livewire\Concerns\Notifies;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Services\IllustrationService;
use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\SectionRegistry;
use App\Services\MediaService;
use App\Services\Template\TemplateDraft;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Template builder: default content (copied into new invitations) or sample content
 * (template preview only) of one section type, edited in the template draft.
 *
 * Only values that differ from what the section would show anyway are stored.
 */
class TemplateSectionContent extends Component
{
    use EditsSectionFields, Notifies, WithFileUploads;

    public const GROUPS = ['default_configuration', 'sample_content'];

    #[Locked]
    public string $templateUlid = '';

    #[Locked]
    public string $type = '';

    #[Locked]
    public string $group = '';

    public ?string $savedAt = null;

    /** Layout of the selected section; only decides which layout-specific fields are shown. */
    public string $variant = '';

    public function mount(string $templateUlid, string $type, string $group, SectionRegistry $registry, ?string $variant = null): void
    {
        Gate::authorize('templates.manage');
        abort_unless(in_array($group, self::GROUPS, true) && $registry->has($type), 404);

        $this->templateUlid = $templateUlid;
        $this->type = $type;
        $this->group = $group;
        $this->variant = $registry->resolveVariant($type, $variant);

        $definition = $this->draft()->definition($this->template(), Auth::user());
        $this->config = $registry->sanitize($type, array_replace($this->base($definition), $definition[$group][$type] ?? []));
    }

    public function save(): void
    {
        Gate::authorize('templates.manage');

        $this->validateConfig();

        $registry = app(SectionRegistry::class);
        $config = $registry->sanitize($this->type, $this->config);

        try {
            $this->draft()->update($this->template(), Auth::user(), function (array $definition) use ($config): array {
                $overrides = $this->overrides($config, $this->base($definition));

                if ($overrides === []) {
                    unset($definition[$this->group][$this->type]);
                } else {
                    $definition[$this->group][$this->type] = $overrides;
                }

                return $definition;
            });
        } catch (ValidationException $exception) {
            $prefix = "{$this->group}.{$this->type}.";

            foreach ($exception->errors() as $key => $messages) {
                $this->addError(str_starts_with($key, $prefix) ? 'config.'.substr($key, strlen($prefix)) : 'config', $messages[0]);
            }

            return;
        }

        $this->savedAt = now()->format('H:i:s');
        $this->dispatch('template-draft-updated');
    }

    /**
     * Sample content only: drop this section's sample values. Sample content falls back to the
     * default content, so the section then shows exactly the default content.
     */
    public function copyFromDefaults(): void
    {
        Gate::authorize('templates.manage');
        abort_unless($this->group === 'sample_content', 404);

        $definition = $this->draft()->update($this->template(), Auth::user(), function (array $definition): array {
            unset($definition['sample_content'][$this->type]);

            return $definition;
        });

        $this->config = $this->base($definition);
        $this->resetErrorBag();
        $this->savedAt = now()->format('H:i:s');
        $this->dispatch('template-draft-updated');
        $this->notify('success', __('Default content copied to the sample content.'));
    }

    /**
     * Gallery "Set cover": use a photo as the cover background in the same content group.
     */
    public function setAsCover(string $path, int $index): void
    {
        Gate::authorize('templates.manage');

        $url = Arr::get($this->config, "{$path}.{$index}");

        if (! is_string($url)) {
            return;
        }

        $this->draft()->update($this->template(), Auth::user(), function (array $definition) use ($url): array {
            $definition[$this->group]['cover'] = [...($definition[$this->group]['cover'] ?? []), 'background_image' => $url];

            return $definition;
        });

        $this->dispatch('template-draft-updated');
    }

    public function render(SectionRegistry $registry, IllustrationService $illustrations): View
    {
        return view('livewire.template-section-content', [
            'fields' => $registry->fields($this->type),
            'hasSampleValues' => $this->group === 'sample_content'
                && ($this->draft()->definition($this->template(), Auth::user())['sample_content'][$this->type] ?? []) !== [],
            'designFeatures' => $this->designFeatures(),
            ...$this->pickerViewData($illustrations),
        ]);
    }

    protected function storeUploadedImage(TemporaryUploadedFile $file): string
    {
        $stored = app(MediaService::class)->storeWebp($file, "templates/{$this->templateUlid}");

        return (string) parse_url(Storage::disk(MediaService::DISK)->url($stored['path']), PHP_URL_PATH);
    }

    /**
     * Template admins may use every illustration, premium ones included.
     */
    protected function canUseIllustration(Illustration $illustration): bool
    {
        return true;
    }

    /**
     * What the section shows without this group's values: registry defaults, plus the
     * template defaults when editing sample content.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function base(array $definition): array
    {
        $registry = app(SectionRegistry::class);
        $base = $registry->defaults($this->type);

        if ($this->group === 'sample_content') {
            $base = array_replace($base, $definition['default_configuration'][$this->type] ?? []);
        }

        return $registry->sanitize($this->type, $base);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function overrides(array $config, array $base): array
    {
        return array_filter($config, fn (mixed $value, string $key): bool => ! array_key_exists($key, $base) || $base[$key] !== $value, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array<int, string>
     */
    private function designFeatures(): array
    {
        $definition = $this->draft()->definition($this->template(), Auth::user());

        return app(DesignRegistry::class)->features($definition['design'] ?? null, $definition['decoration'] ?? []);
    }

    private function template(): InvitationTemplate
    {
        return InvitationTemplate::query()->where('ulid', $this->templateUlid)->firstOrFail();
    }

    private function draft(): TemplateDraft
    {
        return app(TemplateDraft::class);
    }
}
