<?php

namespace App\Livewire;

use App\Enums\IllustrationCategory;
use App\Livewire\Concerns\Notifies;
use App\Models\Illustration;
use App\Services\AuditLogger;
use App\Services\IllustrationService;
use App\Services\MediaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Admin library of 2D illustrations that clients can use instead of photos.
 */
class IllustrationManager extends Component
{
    use Notifies, WithFileUploads;

    private const MAX_FILES_PER_UPLOAD = 20;

    #[Url(except: '')]
    public string $category = '';

    public string $uploadCategory = 'person';

    public bool $uploadPremium = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public function mount(): void
    {
        Gate::authorize('illustrations.manage');

        if ($this->category !== '') {
            $this->uploadCategory = $this->category;
        }
    }

    /**
     * Not called "upload": in the browser that name resolves to Livewire's own $wire.upload().
     */
    public function saveUploads(IllustrationService $illustrations, AuditLogger $auditLogger): void
    {
        Gate::authorize('illustrations.manage');

        $this->validate([
            'uploadCategory' => ['required', Rule::enum(IllustrationCategory::class)],
            'files' => ['required', 'array', 'max:'.self::MAX_FILES_PER_UPLOAD],
            'files.*' => MediaService::imageRules(),
        ], [], ['files' => __('files'), 'files.*' => __('file')]);

        $category = IllustrationCategory::from($this->uploadCategory);

        foreach ($this->files as $file) {
            $illustration = $illustrations->store($file, $category, null, $this->uploadPremium);
            $auditLogger->record('illustration.created', Auth::user(), $illustration, ['category' => $category->value]);
        }

        $this->notify('success', trans_choice(':count illustration added.|:count illustrations added.', count($this->files)));
        $this->files = [];
        $this->category = $category->value;
    }

    public function rename(string $illustrationUlid, string $name): void
    {
        Gate::authorize('illustrations.manage');

        $name = trim($name);

        if ($name !== '' && mb_strlen($name) <= 80) {
            $this->illustration($illustrationUlid)->update(['name' => $name]);
        }
    }

    public function toggleActive(string $illustrationUlid): void
    {
        Gate::authorize('illustrations.manage');

        $illustration = $this->illustration($illustrationUlid);
        $illustration->update(['is_active' => ! $illustration->is_active]);
    }

    public function togglePremium(string $illustrationUlid): void
    {
        Gate::authorize('illustrations.manage');

        $illustration = $this->illustration($illustrationUlid);
        $illustration->update(['is_premium' => ! $illustration->is_premium]);
    }

    public function remove(string $illustrationUlid, IllustrationService $illustrations, AuditLogger $auditLogger): void
    {
        Gate::authorize('illustrations.manage');

        $illustration = $this->illustration($illustrationUlid);
        $deleted = $illustrations->remove($illustration);

        $auditLogger->record($deleted ? 'illustration.deleted' : 'illustration.deactivated', Auth::user(), $illustration);

        $deleted
            ? $this->notify('success', __('Illustration deleted.'))
            : $this->notify('warning', __('This illustration is used by an invitation, so it was hidden from the picker instead of deleted.'));
    }

    public function render(): View
    {
        $filter = IllustrationCategory::tryFrom($this->category);

        return view('livewire.illustration-manager', [
            'illustrations' => Illustration::query()
                ->when($filter, fn ($query) => $query->where('category', $filter))
                ->orderBy('category')
                ->ordered()
                ->get(),
            'categories' => IllustrationCategory::cases(),
            'counts' => Illustration::query()->get(['category'])->countBy(fn (Illustration $illustration): string => $illustration->category->value),
        ]);
    }

    private function illustration(string $illustrationUlid): Illustration
    {
        return Illustration::query()->where('ulid', $illustrationUlid)->firstOrFail();
    }
}
