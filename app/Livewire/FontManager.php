<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Models\Font;
use App\Rules\FontFile;
use App\Services\BunnyFontCatalog;
use App\Services\FontRegistry;
use App\Services\FontService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Admin fonts: install a Bunny Fonts family by name or upload font files. Built-in fonts are listed
 * read-only. Installed fonts appear in the theme pickers of the template builder and invitation editor.
 */
class FontManager extends Component
{
    use Notifies, WithFileUploads, WithPagination;

    private const MAX_FILES = 8;

    private const MAX_FILE_KB = 5120;

    public string $mode = 'bunny';

    public string $bunnyName = '';

    /** @var array{name: string, family: string, category: string, weights: array<int, int>}|null The family found by lookup(). */
    #[Locked]
    public ?array $bunnyMatch = null;

    public string $bunnyCategory = 'serif';

    public string $uploadName = '';

    public string $uploadCategory = 'serif';

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** @var array<int, int|string> Weight per uploaded file, same order as $files. */
    public array $fileWeights = [];

    /** @var array<int, string> Style per uploaded file, same order as $files. */
    public array $fileStyles = [];

    public function mount(): void
    {
        Gate::authorize('fonts.manage');
    }

    public function updatedBunnyName(): void
    {
        $this->bunnyMatch = null;
        $this->resetValidation('bunnyName');
    }

    /**
     * Find the typed name in the Bunny Fonts catalog before anything is installed.
     */
    public function lookup(BunnyFontCatalog $catalog, FontRegistry $fonts): void
    {
        Gate::authorize('fonts.manage');
        $this->bunnyMatch = null;
        $this->validate(['bunnyName' => ['required', 'string', 'max:60']], [], ['bunnyName' => __('font name')]);

        try {
            $match = $catalog->find($this->bunnyName);
        } catch (ConnectionException|RequestException) {
            $this->addError('bunnyName', __('Bunny Fonts cannot be reached right now. Try again in a moment.'));

            return;
        }

        if ($match === null || preg_match(FontService::NAME_PATTERN, $match['name']) !== 1) {
            $this->addError('bunnyName', __('No font with this name in Bunny Fonts. Check the spelling on fonts.bunny.net.'));

            return;
        }

        if ($fonts->has($match['name'])) {
            $this->addError('bunnyName', __('":name" is already installed.', ['name' => $match['name']]));

            return;
        }

        $this->bunnyMatch = $match;
        $this->bunnyCategory = $match['category'];
    }

    public function addBunny(FontService $service, FontRegistry $fonts): void
    {
        Gate::authorize('fonts.manage');

        if ($this->bunnyMatch === null || $fonts->has($this->bunnyMatch['name'])) {
            return;
        }

        $this->validate(['bunnyCategory' => ['required', Rule::in(FontRegistry::CATEGORIES)]]);

        $font = $service->addFromBunny($this->bunnyMatch, $this->bunnyCategory, Auth::user());

        $this->reset('bunnyName', 'bunnyMatch');
        $this->resetPage();
        $this->notify('success', __('":name" installed. It can now be chosen in the theme settings.', ['name' => $font->name]));
    }

    /**
     * Pre-fill each file's weight and style from its name (e.g. "Lora-SemiBoldItalic.woff2"); the admin can change them.
     */
    public function updatedFiles(): void
    {
        $this->fileWeights = [];
        $this->fileStyles = [];

        foreach ($this->files as $index => $file) {
            $name = strtolower($file->getClientOriginalName());
            $this->fileWeights[$index] = match (true) {
                str_contains($name, 'thin') || str_contains($name, 'hairline') => 100,
                str_contains($name, 'extralight') || str_contains($name, 'ultralight') => 200,
                str_contains($name, 'light') => 300,
                str_contains($name, 'medium') => 500,
                str_contains($name, 'semibold') || str_contains($name, 'demibold') => 600,
                str_contains($name, 'extrabold') || str_contains($name, 'ultrabold') => 800,
                str_contains($name, 'black') || str_contains($name, 'heavy') => 900,
                str_contains($name, 'bold') => 700,
                default => 400,
            };
            $this->fileStyles[$index] = str_contains($name, 'italic') || str_contains($name, 'oblique') ? 'italic' : 'normal';
        }
    }

    public function saveUpload(FontService $service, FontRegistry $fonts): void
    {
        Gate::authorize('fonts.manage');

        $this->validate([
            'uploadName' => ['required', 'string', 'max:60', 'regex:'.FontService::NAME_PATTERN,
                fn (string $attribute, mixed $value, \Closure $fail) => $fonts->has(trim((string) $value)) ? $fail(__('This font name is already in use.')) : null],
            'uploadCategory' => ['required', Rule::in(FontRegistry::CATEGORIES)],
            'files' => ['required', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'max:'.self::MAX_FILE_KB, new FontFile],
            'fileWeights.*' => ['required', Rule::in(FontService::WEIGHTS)],
            'fileStyles.*' => ['required', Rule::in(FontService::STYLES)],
        ], [
            'uploadName.regex' => __('Use letters, digits, spaces and hyphens only.'),
        ], [
            'uploadName' => __('font name'),
            'files' => __('files'),
            'files.*' => __('file'),
        ]);

        $font = $service->addUpload(trim($this->uploadName), $this->uploadCategory, collect($this->files)->map(fn (TemporaryUploadedFile $file, int $index): array => [
            'file' => $file,
            'weight' => (int) ($this->fileWeights[$index] ?? 400),
            'style' => (string) ($this->fileStyles[$index] ?? 'normal'),
        ])->values()->all(), Auth::user());

        $this->reset('uploadName', 'files', 'fileWeights', 'fileStyles');
        $this->resetPage();
        $this->notify('success', __('":name" installed. It can now be chosen in the theme settings.', ['name' => $font->name]));
    }

    public function toggleActive(string $fontUlid, FontService $service): void
    {
        Gate::authorize('fonts.manage');
        $font = $this->font($fontUlid);

        $service->setActive($font, ! $font->is_active, Auth::user());
        $this->notify('success', $font->is_active ? __('Font shown in the theme settings again.') : __('Font hidden from the theme settings. Templates and invitations that use it keep it.'));
    }

    public function remove(string $fontUlid, FontService $service): void
    {
        Gate::authorize('fonts.manage');

        $service->delete($this->font($fontUlid), Auth::user())
            ? $this->notify('success', __('Font deleted.'))
            : $this->notify('warning', __('A template or invitation uses this font, so it cannot be deleted. Hide it instead.'));
    }

    public function render(FontRegistry $fonts, FontService $service): View
    {
        $installed = Font::query()->withCount('files')->orderBy('name')->paginate(20);
        $builtin = collect(config('invitation.fonts'))->keys();

        return view('livewire.font-manager', [
            'installed' => $installed,
            'usage' => $installed->getCollection()->mapWithKeys(fn (Font $font): array => [$font->ulid => $service->usageCount($font->name)]),
            'builtin' => collect(config('invitation.fonts'))->map(fn (array $font, string $name): array => [...$font, 'name' => $name])->groupBy('category'),
            // One stylesheet for every preview on this page (hidden fonts included).
            'previewStylesheet' => $fonts->stylesheetUrl([...$builtin->all(), ...$installed->getCollection()->pluck('name')->all()]),
            'categories' => FontRegistry::CATEGORIES,
            'weights' => FontService::WEIGHTS,
            'maxFileMb' => (int) (self::MAX_FILE_KB / 1024),
            'maxFiles' => self::MAX_FILES,
        ]);
    }

    private function font(string $fontUlid): Font
    {
        return Font::query()->where('ulid', $fontUlid)->firstOrFail();
    }
}
