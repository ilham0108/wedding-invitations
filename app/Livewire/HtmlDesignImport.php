<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Models\TemplateCategory;
use App\Services\Invitation\SectionRegistry;
use App\Services\Template\HtmlDesign\HtmlDesignImporter;
use App\Services\Template\HtmlDesign\HtmlDesignInspector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Super admin only: check an HTML design package, then import it as a draft template.
 */
class HtmlDesignImport extends Component
{
    use Notifies;
    use WithFileUploads;

    public $package = null;

    public string $name = '';

    public string $category = '';

    /** @var array{errors: array<int, string>, warnings: array<int, string>, summary: array{sections: array<int, string>, fields: int, assets: int}}|null */
    public ?array $report = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updatedPackage(HtmlDesignInspector $inspector): void
    {
        $this->authorizeAdmin();
        $this->report = null;

        $this->validate(['package' => ['required', 'file', 'extensions:zip', 'mimes:zip', 'max:12288']], [], ['package' => __('file')]);

        $result = $inspector->inspect((string) $this->package->getRealPath(), HtmlDesignImporter::newKey());
        $this->report = ['errors' => $result['errors'], 'warnings' => $result['warnings'], 'summary' => $result['summary']];
    }

    public function import(HtmlDesignImporter $importer): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'package' => ['required', 'file', 'extensions:zip', 'mimes:zip', 'max:12288'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'exists:template_categories,slug'],
        ], [], ['package' => __('file'), 'name' => __('Name'), 'category' => __('Category')]);

        $category = TemplateCategory::query()->where('slug', $this->category)->firstOrFail();

        try {
            $template = $importer->import((string) $this->package->getRealPath(), $this->name, $category->getKey(), Auth::user());
        } catch (ValidationException $exception) {
            $this->report = ['errors' => collect($exception->errors())->flatten()->all(), 'warnings' => [], 'summary' => ['sections' => [], 'fields' => 0, 'assets' => 0]];

            return;
        }

        $this->notify('success', __('Template imported as draft “:name”. Check it in the builder, then publish it.', ['name' => $template->name]));
        $this->redirectRoute('admin.templates.builder', $template);
    }

    public function render(SectionRegistry $registry): View
    {
        return view('livewire.html-design-import', [
            'categories' => TemplateCategory::query()->orderBy('name')->pluck('name', 'slug')->all(),
            'sectionLabels' => collect(HtmlDesignInspector::SECTIONS)->mapWithKeys(fn (string $type): array => [$type => $registry->label($type)])->all(),
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);
    }
}
