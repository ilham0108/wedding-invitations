<?php

namespace App\Livewire;

use App\Enums\TemplateStatus;
use App\Livewire\Concerns\Notifies;
use App\Models\InvitationTemplate;
use App\Models\TemplateCategory;
use App\Services\MediaService;
use App\Services\Template\TemplatePackage;
use App\Services\Template\TemplateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Admin: template catalog data and lifecycle (rules.md section 50).
 * Categories are referenced by slug and templates by ULID, never by database ID.
 */
class TemplateManager extends Component
{
    use Notifies, WithFileUploads;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $status = '';

    public bool $showForm = false;

    #[Locked]
    public ?string $editingUlid = null;

    /** @var array{name: string, slug: string, category: string, description: string, is_premium: bool, sort_order: int|string} */
    public array $form = [];

    public ?TemporaryUploadedFile $thumbnail = null;

    /** An exported template (.zip) to import; imported as soon as it is uploaded. */
    public ?TemporaryUploadedFile $package = null;

    public function mount(): void
    {
        Gate::authorize('templates.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingUlid = null;
        $this->thumbnail = null;
        $this->form = [
            'name' => '',
            'slug' => '',
            'category' => $this->category ?: (string) TemplateCategory::query()->orderBy('sort_order')->value('slug'),
            'description' => '',
            'is_premium' => false,
            'sort_order' => (int) InvitationTemplate::query()->max('sort_order') + 1,
        ];
        $this->showForm = true;
    }

    public function edit(string $templateUlid): void
    {
        $template = $this->template($templateUlid)->load('category');

        $this->resetValidation();
        $this->editingUlid = $template->ulid;
        $this->thumbnail = null;
        $this->form = [
            'name' => $template->name,
            'slug' => $template->slug,
            'category' => $template->category->slug,
            'description' => (string) $template->description,
            'is_premium' => $template->is_premium,
            'sort_order' => $template->sort_order,
        ];
        $this->showForm = true;
    }

    public function updatedFormName(string $name): void
    {
        if (! $this->editingUlid && $this->form['slug'] === '') {
            $this->form['slug'] = Str::slug($name);
        }
    }

    public function save(TemplateService $templates): void
    {
        Gate::authorize('templates.manage');

        $template = $this->editingUlid ? $this->template($this->editingUlid) : null;

        $this->validate([
            'form.name' => ['required', 'string', 'max:120'],
            'form.slug' => ['required', 'string', 'max:100', 'alpha_dash:ascii', Rule::unique('invitation_templates', 'slug')->ignore($template?->getKey())],
            'form.category' => ['required', Rule::exists('template_categories', 'slug')],
            'form.description' => ['nullable', 'string', 'max:500'],
            'form.is_premium' => ['boolean'],
            'form.sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'thumbnail' => ['nullable', ...MediaService::imageRules()],
        ], [], [
            'form.name' => __('name'),
            'form.slug' => 'slug',
            'form.category' => __('category'),
            'form.description' => __('description'),
            'form.sort_order' => __('display order'),
            'thumbnail' => __('thumbnail'),
        ]);

        $attributes = [
            'name' => trim($this->form['name']),
            'slug' => Str::lower($this->form['slug']),
            'template_category_id' => TemplateCategory::query()->where('slug', $this->form['category'])->value('id'),
            'description' => trim($this->form['description']) ?: null,
            'is_premium' => (bool) $this->form['is_premium'],
            'sort_order' => (int) $this->form['sort_order'],
        ];

        $template = $template
            ? $templates->update($template, $attributes, Auth::user())
            : $templates->create($attributes, Auth::user());

        if ($this->thumbnail) {
            $templates->replaceThumbnail($template, $this->thumbnail);
        }

        $this->showForm = false;
        $this->thumbnail = null;
        $this->notify('success', __('Template saved.'));
    }

    public function removeThumbnail(TemplateService $templates): void
    {
        Gate::authorize('templates.manage');

        if ($this->editingUlid) {
            $templates->removeThumbnail($this->template($this->editingUlid));
        }

        $this->thumbnail = null;
    }

    public function updatedPackage(TemplatePackage $packages): void
    {
        Gate::authorize('templates.manage');

        $this->validate(['package' => ['required', 'file', 'extensions:zip', 'mimes:zip', 'max:12288']], [], ['package' => __('file')]);

        try {
            $template = $packages->import((string) $this->package->getRealPath(), Auth::user());
        } catch (ValidationException $exception) {
            $this->package = null;
            $this->addError('package', collect($exception->errors())->flatten()->first());

            return;
        }

        $this->package = null;
        $this->notify('success', __('Template imported as draft “:name”. Check it in the builder, then publish it.', ['name' => $template->name]));
    }

    public function duplicate(string $templateUlid, TemplateService $templates): void
    {
        Gate::authorize('templates.manage');

        $copy = $templates->duplicate($this->template($templateUlid), Auth::user());

        $this->notify('success', __('Template duplicated as draft “:name”.', ['name' => $copy->name]));
    }

    public function publish(string $templateUlid, TemplateService $templates): void
    {
        Gate::authorize('templates.manage');

        try {
            $templates->publish($this->template($templateUlid), Auth::user());
        } catch (ValidationException $exception) {
            $this->notify('error', collect($exception->errors())->flatten()->first());

            return;
        }

        $this->notify('success', __('Template published. Clients can now use it.'));
    }

    public function unpublish(string $templateUlid, TemplateService $templates): void
    {
        Gate::authorize('templates.manage');

        $templates->unpublish($this->template($templateUlid), Auth::user());

        $this->notify('success', __('Template unpublished. Existing invitations are not affected.'));
    }

    public function remove(string $templateUlid, TemplateService $templates): void
    {
        Gate::authorize('templates.manage');

        $templates->remove($this->template($templateUlid), Auth::user())
            ? $this->notify('success', __('Template deleted.'))
            : $this->notify('warning', __('Invitations were created from this template, so it was unpublished instead of deleted.'));
    }

    public function render(): View
    {
        $status = TemplateStatus::tryFrom($this->status);
        $search = trim($this->search);

        return view('livewire.template-manager', [
            'templates' => InvitationTemplate::query()
                ->with('category:id,name,slug')
                ->withCount('invitations')
                ->when($this->category !== '', fn ($query) => $query->whereHas('category', fn ($query) => $query->where('slug', $this->category)))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('slug', 'like', '%'.addcslashes($search, '%_\\').'%')))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'categories' => TemplateCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'is_active']),
            'statuses' => TemplateStatus::cases(),
            'editing' => $this->editingUlid ? $this->template($this->editingUlid) : null,
        ]);
    }

    private function template(string $templateUlid): InvitationTemplate
    {
        return InvitationTemplate::query()->where('ulid', $templateUlid)->firstOrFail();
    }
}
