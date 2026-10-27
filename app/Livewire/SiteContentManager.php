<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Services\MediaService;
use App\Services\SiteContentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Admin CMS (rules.md section 52): one tab per marketing-site section, plus the static pages tab.
 */
class SiteContentManager extends Component
{
    use Notifies, WithFileUploads;

    /** List items with an icon; the ones also in DESCRIBED have a description. */
    private const DESCRIBED = ['steps', 'features'];

    #[Url(except: 'hero')]
    public string $tab = 'hero';

    /** @var array<string, mixed> The section being edited. */
    public array $form = [];

    /** Photo for the current section (sections whose defaults have "image"). */
    public ?TemporaryUploadedFile $sectionImage = null;

    public function mount(SiteContentService $content): void
    {
        Gate::authorize('cms.manage');

        $this->normalizeTab($content);
        $this->loadForm($content);
    }

    public function updatedTab(SiteContentService $content): void
    {
        $this->normalizeTab($content);
        $this->resetValidation();
        $this->sectionImage = null;
        $this->loadForm($content);
    }

    public function save(SiteContentService $content): void
    {
        Gate::authorize('cms.manage');
        abort_unless(array_key_exists($this->tab, $content->sections()), 404);

        $rules = collect($content->rules($this->tab))->mapWithKeys(fn (array $rule, string $key): array => ["form.{$key}" => $rule])->all();
        $validated = $this->validate($rules, [], $this->attributes())['form'] ?? [];

        $content->save($this->tab, $validated, Auth::user());
        $this->loadForm($content);

        $this->notify('success', __('Saved. The site shows the new content now.'));
    }

    public function addItem(): void
    {
        $max = config('site-content.max_items')[$this->tab] ?? 0;

        if (! is_array($this->form['items'] ?? null) || count($this->form['items']) >= $max) {
            return;
        }

        $icon = config('site-content.icons')[0];

        $this->form['items'][] = match (true) {
            $this->tab === 'faq' => ['question' => '', 'answer' => ''],
            in_array($this->tab, self::DESCRIBED, true) => ['icon' => $icon, 'title' => '', 'description' => ''],
            default => ['icon' => $icon, 'title' => ''],
        };
    }

    public function removeItem(int $index): void
    {
        unset($this->form['items'][$index]);
        $this->form['items'] = array_values($this->form['items'] ?? []);
    }

    public function moveItem(int $index, int $direction): void
    {
        $items = $this->form['items'] ?? [];
        $target = $index + ($direction < 0 ? -1 : 1);

        if (! isset($items[$index], $items[$target])) {
            return;
        }

        [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
        $this->form['items'] = $items;
    }

    public function updatedSectionImage(SiteContentService $content): void
    {
        Gate::authorize('cms.manage');
        abort_unless($content->hasImage($this->tab), 404);

        $this->validate(['sectionImage' => ['required', ...MediaService::imageRules()]], [], ['sectionImage' => __('image')]);

        $content->replaceImage($this->tab, $this->sectionImage, Auth::user());
        $this->sectionImage = null;
        $this->form['image'] = $content->section($this->tab)['image'];
        $this->notify('success', __('Image saved.'));
    }

    public function removeSectionImage(SiteContentService $content): void
    {
        Gate::authorize('cms.manage');
        abort_unless($content->hasImage($this->tab), 404);

        $content->replaceImage($this->tab, null, Auth::user());
        $this->form['image'] = null;
    }

    public function render(SiteContentService $content): View
    {
        return view('livewire.site-content-manager', [
            'sections' => $content->sections(),
            'icons' => config('site-content.icons'),
            'maxItems' => config('site-content.max_items')[$this->tab] ?? 0,
            'described' => in_array($this->tab, self::DESCRIBED, true),
            'imageHelp' => [
                'hero' => __('Photo filling the right side of the hero (e.g. a phone with flowers). Without one, an illustrated phone is shown.'),
                'steps' => __('Picture beside the steps, e.g. a phone showing an invitation. Without one, an illustrated phone is shown.'),
                'cta' => __('Wide background photo behind the call to action. Without one, a soft background with leaves is shown.'),
            ][$this->tab] ?? null,
        ]);
    }

    private function normalizeTab(SiteContentService $content): void
    {
        if ($this->tab !== 'pages' && ! array_key_exists($this->tab, $content->sections())) {
            $this->tab = 'hero';
        }
    }

    private function loadForm(SiteContentService $content): void
    {
        $this->form = $this->tab === 'pages' ? [] : $content->section($this->tab);
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'form.heading' => mb_strtolower(__('Heading')),
            'form.title' => mb_strtolower(__('Title')),
            'form.items.*.title' => mb_strtolower(__('Title')),
            'form.items.*.question' => mb_strtolower(__('Question')),
            'form.items.*.answer' => mb_strtolower(__('Answer')),
            'form.primary_label' => mb_strtolower(__('Sign-up button')),
            'form.button_label' => mb_strtolower(__('Button label')),
        ];
    }
}
