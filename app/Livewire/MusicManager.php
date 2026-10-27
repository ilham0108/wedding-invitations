<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Models\MusicTrack;
use App\Services\AuditLogger;
use App\Services\MusicService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Admin library of background music clients pick for their invitations (rules.md section 30).
 */
class MusicManager extends Component
{
    use Notifies, WithFileUploads;

    private const MAX_FILES_PER_UPLOAD = 10;

    public bool $uploadPremium = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public function mount(): void
    {
        Gate::authorize('music.manage');
    }

    /**
     * Not called "upload": in the browser that name resolves to Livewire's own $wire.upload().
     */
    public function saveUploads(MusicService $music, AuditLogger $auditLogger): void
    {
        Gate::authorize('music.manage');

        $this->validate([
            'files' => ['required', 'array', 'max:'.self::MAX_FILES_PER_UPLOAD],
            'files.*' => MusicService::audioRules((int) config('invitation.music.max_library_upload_kb')),
        ], [], ['files' => __('files'), 'files.*' => __('file')]);

        foreach ($this->files as $file) {
            $track = $music->storeTrack($file, null, null, $this->uploadPremium);
            $auditLogger->record('music.created', Auth::user(), $track);
        }

        $this->notify('success', trans_choice(':count song added.|:count songs added.', count($this->files)));
        $this->files = [];
    }

    public function rename(string $trackUlid, string $field, string $value): void
    {
        Gate::authorize('music.manage');

        $value = trim($value);

        match (true) {
            $field === 'title' && $value !== '' && mb_strlen($value) <= 120 => $this->track($trackUlid)->update(['title' => $value]),
            $field === 'artist' && mb_strlen($value) <= 120 => $this->track($trackUlid)->update(['artist' => $value ?: null]),
            default => null,
        };
    }

    public function toggleActive(string $trackUlid): void
    {
        Gate::authorize('music.manage');

        $track = $this->track($trackUlid);
        $track->update(['is_active' => ! $track->is_active]);
    }

    public function togglePremium(string $trackUlid): void
    {
        Gate::authorize('music.manage');

        $track = $this->track($trackUlid);
        $track->update(['is_premium' => ! $track->is_premium]);
    }

    public function remove(string $trackUlid, MusicService $music, AuditLogger $auditLogger): void
    {
        Gate::authorize('music.manage');

        $track = $this->track($trackUlid);
        $deleted = $music->removeTrack($track);

        $auditLogger->record($deleted ? 'music.deleted' : 'music.deactivated', Auth::user(), $track);

        $deleted
            ? $this->notify('success', __('Song deleted.'))
            : $this->notify('warning', __('This song is used by an invitation, so it was hidden from the picker instead of deleted.'));
    }

    public function render(): View
    {
        return view('livewire.music-manager', [
            'tracks' => MusicTrack::query()->ordered()->get(),
            'maxUploadMb' => (int) round(config('invitation.music.max_library_upload_kb') / 1024),
        ]);
    }

    private function track(string $trackUlid): MusicTrack
    {
        return MusicTrack::query()->where('ulid', $trackUlid)->firstOrFail();
    }
}
