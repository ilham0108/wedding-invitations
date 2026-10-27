<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Services\Guest\GuestImportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * CSV/Excel import with a preview step before anything is saved (rules.md section 34).
 */
class GuestImport extends Component
{
    use Notifies, ResolvesEditableInvitation, WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    /** @var array{token: string, total: int, valid_count: int, invalid_count: int, invalid: array<int, array<string, mixed>>, sample: array<int, array<string, mixed>>}|null */
    #[Locked]
    public ?array $preview = null;

    public function mount(string $invitationUlid): void
    {
        $this->invitationUlid = $invitationUlid;
        $this->invitation();
    }

    public function updatedFile(GuestImportService $imports): void
    {
        $this->validate(['file' => GuestImportService::fileRules()]);

        $this->preview = $imports->analyze($this->invitation(), Auth::user(), $this->file);
        $this->file = null;
    }

    public function confirm(GuestImportService $imports): void
    {
        if ($this->preview === null || $this->preview['valid_count'] === 0) {
            return;
        }

        $outcome = $imports->commit($this->invitation(), Auth::user(), $this->preview['token']);

        $outcome['queued']
            ? $this->notify('info', __(':count guests are being imported in the background.', ['count' => $outcome['count']]))
            : $this->notify('success', __(':count guests imported.', ['count' => $outcome['count']]));
        $this->preview = null;
        $this->dispatch('guests-imported');
    }

    public function cancel(): void
    {
        $this->reset(['file', 'preview']);
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.guest-import');
    }
}
