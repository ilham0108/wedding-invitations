<?php

namespace App\Livewire;

use App\Enums\EventType;
use App\Livewire\Concerns\Notifies;
use App\Models\TemplateCategory;
use App\Services\Template\TemplateCategoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Admin: template categories. Each category serves one event type in the creation wizard.
 */
class TemplateCategoryManager extends Component
{
    use Notifies;

    private const ICON_PATTERN = '/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/';

    public bool $showForm = false;

    /** The category being edited, by slug (categories have no public ULID). */
    #[Locked]
    public ?string $editingSlug = null;

    /** @var array{name: string, slug: string, event_type: string, description: string, icon: string, sort_order: int|string, is_active: bool} */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('template-categories.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingSlug = null;
        $this->form = [
            'name' => '',
            'slug' => '',
            'event_type' => EventType::Wedding->value,
            'description' => '',
            'icon' => EventType::Wedding->icon(),
            'sort_order' => (int) TemplateCategory::query()->max('sort_order') + 1,
            'is_active' => true,
        ];
        $this->showForm = true;
    }

    public function edit(string $slug): void
    {
        $category = $this->category($slug);

        $this->resetValidation();
        $this->editingSlug = $category->slug;
        $this->form = [
            'name' => $category->name,
            'slug' => $category->slug,
            'event_type' => $category->event_type->value,
            'description' => (string) $category->description,
            'icon' => (string) $category->icon,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];
        $this->showForm = true;
    }

    public function updatedFormName(string $name): void
    {
        if (! $this->editingSlug && $this->form['slug'] === '') {
            $this->form['slug'] = Str::slug($name);
        }
    }

    public function updatedFormEventType(string $eventType): void
    {
        if (! $this->editingSlug && ($type = EventType::tryFrom($eventType))) {
            $this->form['icon'] = $type->icon();
        }
    }

    public function save(TemplateCategoryService $categories): void
    {
        Gate::authorize('template-categories.manage');

        $category = $this->editingSlug ? $this->category($this->editingSlug) : new TemplateCategory;

        $this->validate([
            'form.name' => ['required', 'string', 'max:80'],
            'form.slug' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('template_categories', 'slug')->ignore($category->getKey())],
            'form.event_type' => ['required', Rule::enum(EventType::class)],
            'form.description' => ['nullable', 'string', 'max:255'],
            'form.icon' => ['nullable', 'string', 'max:50', 'regex:'.self::ICON_PATTERN],
            'form.sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'form.is_active' => ['boolean'],
        ], [], [
            'form.name' => __('name'),
            'form.slug' => 'slug',
            'form.event_type' => __('event type'),
            'form.description' => __('description'),
            'form.icon' => __('icon'),
            'form.sort_order' => __('display order'),
        ]);

        $categories->save($category, [
            'name' => trim($this->form['name']),
            'slug' => Str::lower($this->form['slug']),
            'event_type' => $this->form['event_type'],
            'description' => trim($this->form['description']) ?: null,
            'icon' => trim($this->form['icon']) ?: null,
            'sort_order' => (int) $this->form['sort_order'],
            'is_active' => (bool) $this->form['is_active'],
        ], Auth::user());

        $this->showForm = false;
        $this->notify('success', __('Category saved.'));
    }

    public function toggleActive(string $slug): void
    {
        Gate::authorize('template-categories.manage');

        $category = $this->category($slug);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function remove(string $slug, TemplateCategoryService $categories): void
    {
        Gate::authorize('template-categories.manage');

        $categories->remove($this->category($slug), Auth::user())
            ? $this->notify('success', __('Category deleted.'))
            : $this->notify('warning', __('This category still has templates, so it was deactivated instead of deleted.'));
    }

    public function render(): View
    {
        return view('livewire.template-category-manager', [
            'categories' => TemplateCategory::query()
                ->withCount(['templates', 'templates as published_templates_count' => fn ($query) => $query->published()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'eventTypes' => EventType::cases(),
        ]);
    }

    private function category(string $slug): TemplateCategory
    {
        return TemplateCategory::query()->where('slug', $slug)->firstOrFail();
    }
}
