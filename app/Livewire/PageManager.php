<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Models\Page;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Admin CMS: static pages of the marketing site (terms, privacy, …), written in Markdown.
 */
class PageManager extends Component
{
    use Notifies;

    public bool $editing = false;

    public bool $preview = false;

    #[Locked]
    public ?string $pageUlid = null;

    /** @var array{title: string, slug: string, body: string, meta_description: string, is_published: bool, show_in_footer: bool, sort_order: int|string} */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('cms.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->pageUlid = null;
        $this->preview = false;
        $this->form = ['title' => '', 'slug' => '', 'body' => '', 'meta_description' => '', 'is_published' => false, 'show_in_footer' => true, 'sort_order' => (int) Page::query()->max('sort_order') + 1];
        $this->editing = true;
    }

    public function edit(string $pageUlid): void
    {
        $page = $this->page($pageUlid);

        $this->resetValidation();
        $this->pageUlid = $page->ulid;
        $this->preview = false;
        $this->form = [
            'title' => $page->title,
            'slug' => $page->slug,
            'body' => $page->body,
            'meta_description' => (string) $page->meta_description,
            'is_published' => $page->is_published,
            'show_in_footer' => $page->show_in_footer,
            'sort_order' => $page->sort_order,
        ];
        $this->editing = true;
    }

    public function updatedFormTitle(string $title): void
    {
        if ($this->pageUlid === null) {
            $this->form['slug'] = Str::slug($title);
        }
    }

    public function save(AuditLogger $auditLogger): void
    {
        Gate::authorize('cms.manage');

        $page = $this->pageUlid ? $this->page($this->pageUlid) : new Page;

        $validated = $this->validate([
            'form.title' => ['required', 'string', 'max:150'],
            'form.slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($page->exists ? $page->getKey() : null)],
            'form.body' => ['required', 'string', 'max:100000'],
            'form.meta_description' => ['nullable', 'string', 'max:300'],
            'form.is_published' => ['boolean'],
            'form.show_in_footer' => ['boolean'],
            'form.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ], [], ['form.title' => mb_strtolower(__('Title')), 'form.slug' => 'slug', 'form.body' => mb_strtolower(__('Content'))])['form'];

        $page->fill([...$validated, 'meta_description' => trim((string) $validated['meta_description']) ?: null])->save();
        $auditLogger->record('cms.page_saved', Auth::user(), $page, ['slug' => $page->slug, 'published' => $page->is_published]);

        $this->editing = false;
        $this->notify('success', __('Page saved.'));
    }

    public function remove(string $pageUlid, AuditLogger $auditLogger): void
    {
        Gate::authorize('cms.manage');

        $page = $this->page($pageUlid);
        $page->delete();
        $auditLogger->record('cms.page_deleted', Auth::user(), $page, ['slug' => $page->slug]);

        $this->notify('success', __('Page deleted.'));
    }

    public function render(): View
    {
        return view('livewire.page-manager', [
            'pages' => Page::query()->ordered()->get(),
            'previewHtml' => $this->editing && $this->preview ? (new Page(['body' => (string) ($this->form['body'] ?? '')]))->html() : null,
        ]);
    }

    private function page(string $pageUlid): Page
    {
        return Page::query()->where('ulid', $pageUlid)->firstOrFail();
    }
}
