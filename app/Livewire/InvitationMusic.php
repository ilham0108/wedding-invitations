<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Models\MusicTrack;
use App\Services\MusicService;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Editor "Music" panel (rules.md section 30): turn music on, pick a library song or upload an MP3,
 * and choose where the song starts. Premium songs and uploads follow the owner's plan.
 */
class InvitationMusic extends Component
{
    use Notifies, ResolvesEditableInvitation, WithFileUploads;

    /** @var array{enabled: bool, source: string, track: ?string, upload: ?string, start_at: int, loop: bool} */
    public array $music = [];

    public ?TemporaryUploadedFile $song = null;

    public function mount(string $invitationUlid, MusicService $music): void
    {
        $this->invitationUlid = $invitationUlid;
        $this->music = $music->normalize($this->invitation()->settings?->music);
    }

    /**
     * Only the switches are bound to inputs; the song itself changes through chooseTrack() and the upload,
     * so a hand-made request cannot point the player at a song the plan does not allow.
     */
    public function updatedMusic(MusicService $music): void
    {
        $stored = $music->normalize($this->invitation()->settings?->music);

        $this->save($music, [
            ...$stored,
            'enabled' => (bool) ($this->music['enabled'] ?? false),
            'start_at' => (int) ($this->music['start_at'] ?? 0),
            'loop' => (bool) ($this->music['loop'] ?? true),
        ]);
    }

    public function chooseTrack(string $trackUlid, MusicService $music, SubscriptionLimitService $limits): void
    {
        $track = MusicTrack::query()->active()->where('ulid', $trackUlid)->firstOrFail();

        if (! $limits->canUseMusicTrack($this->invitation()->user, $track)) {
            $this->notify('warning', __('This song is part of a higher plan.'));

            return;
        }

        $this->save($music, [...$this->music, 'enabled' => true, 'source' => 'library', 'track' => $track->ulid]);
    }

    public function useUpload(MusicService $music): void
    {
        if ($music->upload($this->invitation(), $this->music['upload']) === null) {
            return;
        }

        $this->save($music, [...$this->music, 'enabled' => true, 'source' => 'upload']);
    }

    public function updatedSong(MusicService $music, SubscriptionLimitService $limits): void
    {
        abort_unless($limits->canUploadMusic($this->invitation()->user), 403);

        $this->validate(['song' => ['required', ...MusicService::audioRules((int) config('invitation.music.max_upload_kb'))]], [], ['song' => __('song')]);

        $media = $music->storeUpload($this->invitation(), $this->song);
        $this->song = null;

        $this->save($music, [...$this->music, 'enabled' => true, 'source' => 'upload', 'upload' => $media->ulid]);
        $this->notify('success', __('Song uploaded.'));
    }

    public function render(MusicService $music, SubscriptionLimitService $limits): View
    {
        $invitation = $this->invitation();
        $owner = $invitation->user;

        return view('livewire.invitation-music', [
            'tracks' => $music->library(),
            'canUsePremium' => $limits->enabled($owner, 'premium_music'),
            'canUpload' => $limits->canUploadMusic($owner),
            'upload' => $music->upload($invitation, $this->music['upload']),
            'maxUploadMb' => (int) round(config('invitation.music.max_upload_kb') / 1024),
            'maxStartAt' => (int) config('invitation.music.max_start_at'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function save(MusicService $music, array $values): void
    {
        $music->update($this->invitation(), $values);
        $this->music = $music->normalize($values);
        $this->dispatch('invitation-updated');
    }
}
