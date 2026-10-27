<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\InvitationSetting;
use App\Models\InvitationTemplate;
use App\Models\Media;
use App\Models\MusicTrack;
use App\Models\User;
use App\Rules\AudioFile;
use App\Services\Subscription\SubscriptionLimitService;
use App\Support\AudioFormat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Background music (rules.md section 30): the admin music library, songs clients upload
 * (MP3 or audio-only M4A, see AudioFormat),
 * and the invitation's music setting. The setting stores ULIDs (library track or uploaded
 * media), never URLs, so a client cannot point the player at an arbitrary file.
 */
class MusicService
{
    public const COLLECTION = 'music';

    /**
     * Validation rules for an uploaded song. The name may say ".mp3" while the contents are M4A
     * (common for songs taken from YouTube); AudioFile checks the contents.
     *
     * @return array<int, mixed>
     */
    public static function audioRules(int $maxKilobytes): array
    {
        return ['file', 'extensions:mp3,m4a', 'max:'.$maxKilobytes, new AudioFile];
    }

    /**
     * @return Collection<int, MusicTrack>
     */
    public function library(): Collection
    {
        return MusicTrack::query()->active()->ordered()->get();
    }

    public function storeTrack(UploadedFile $file, ?string $title, ?string $artist, bool $isPremium): MusicTrack
    {
        $path = $file->storeAs('music/library', $this->fileName($file), ['disk' => MediaService::DISK, 'visibility' => 'public']);

        return MusicTrack::query()->create([
            'title' => Str::limit(trim((string) $title) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 120, ''),
            'artist' => Str::limit(trim((string) $artist), 120, '') ?: null,
            'disk' => MediaService::DISK,
            'path' => $path,
            'size' => (int) $file->getSize(),
            'is_premium' => $isPremium,
            'sort_order' => (int) MusicTrack::query()->max('sort_order') + 1,
        ]);
    }

    /**
     * Whether any invitation or template has picked this track (enabled or not).
     */
    public function isInUse(MusicTrack $track): bool
    {
        return InvitationSetting::query()->where('music', 'like', '%'.$track->ulid.'%')->exists()
            || InvitationTemplate::query()->where('music', 'like', '%'.$track->ulid.'%')->exists();
    }

    /**
     * A template's default music in canonical form: a library track that exists, or [] for none.
     * Unknown tracks (e.g. in a package imported from another site) are dropped.
     *
     * @param  array<string, mixed>|null  $music
     * @return array{track?: string, start_at?: int, loop?: bool}
     */
    public function normalizeTemplateMusic(?array $music): array
    {
        $normalized = $this->normalize([...($music ?? []), 'enabled' => true, 'source' => 'library']);

        if ($normalized['track'] === null || ! MusicTrack::query()->where('ulid', $normalized['track'])->exists()) {
            return [];
        }

        return ['track' => $normalized['track'], 'start_at' => $normalized['start_at'], 'loop' => $normalized['loop']];
    }

    /**
     * What the template preview plays, or null when the template has no music.
     *
     * @return array{url: string, start_at: int, loop: bool}|null
     */
    public function forTemplate(InvitationTemplate $template): ?array
    {
        $music = $template->music ?? [];
        $url = isset($music['track']) ? MusicTrack::query()->where('ulid', $music['track'])->first()?->url() : null;

        return $url === null ? null : ['url' => $url, 'start_at' => (int) ($music['start_at'] ?? 0), 'loop' => (bool) ($music['loop'] ?? true)];
    }

    /**
     * The music setting a new invitation starts with: the template's song, unless the owner's
     * plan does not include it (premium song), the song was removed or hidden.
     *
     * @return array{enabled: bool, source: string, track: ?string, upload: ?string, start_at: int, loop: bool}|null
     */
    public function initialForInvitation(InvitationTemplate $template, User $owner): ?array
    {
        $music = $template->music ?? [];
        $track = isset($music['track']) ? MusicTrack::query()->active()->where('ulid', $music['track'])->first() : null;

        if ($track === null || ! app(SubscriptionLimitService::class)->canUseMusicTrack($owner, $track)) {
            return null;
        }

        return $this->normalize([...$music, 'enabled' => true, 'source' => 'library', 'track' => $track->ulid]);
    }

    /**
     * Delete when unused; otherwise only hide it from the picker so invitations keep playing it.
     *
     * @return bool true when deleted, false when it was deactivated instead
     */
    public function removeTrack(MusicTrack $track): bool
    {
        if ($this->isInUse($track)) {
            $track->update(['is_active' => false]);

            return false;
        }

        if (! $track->isBundled()) {
            Storage::disk($track->disk)->delete($track->path);
        }

        $track->delete();

        return true;
    }

    /**
     * Store a client's own song; the previous upload of this invitation is removed.
     */
    public function storeUpload(Invitation $invitation, UploadedFile $file): Media
    {
        $previous = $this->uploads($invitation);
        $path = $file->storeAs("invitations/{$invitation->ulid}/music", $this->fileName($file), ['disk' => MediaService::DISK, 'visibility' => 'public']);

        $media = new Media([
            'invitation_id' => $invitation->getKey(),
            'collection' => self::COLLECTION,
            'disk' => MediaService::DISK,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => AudioFormat::mimeType($this->format($file)),
            'size' => (int) $file->getSize(),
        ]);
        $media->user()->associate($invitation->user_id);
        $media->save();

        $previous->each(function (Media $old): void {
            Storage::disk($old->disk)->delete($old->path);
            $old->forceDelete();
        });

        return $media;
    }

    public function upload(Invitation $invitation, ?string $mediaUlid): ?Media
    {
        return $mediaUlid === null ? null : $invitation->media()->where('collection', self::COLLECTION)->where('ulid', $mediaUlid)->first();
    }

    /**
     * The invitation's setting with every key present and every value in range.
     *
     * @param  array<string, mixed>|null  $music
     * @return array{enabled: bool, source: string, track: ?string, upload: ?string, start_at: int, loop: bool}
     */
    public function normalize(?array $music): array
    {
        $music ??= [];

        return [
            'enabled' => (bool) ($music['enabled'] ?? false),
            'source' => ($music['source'] ?? null) === 'upload' ? 'upload' : 'library',
            'track' => is_string($music['track'] ?? null) && Str::isUlid($music['track']) ? Str::lower($music['track']) : null,
            'upload' => is_string($music['upload'] ?? null) && Str::isUlid($music['upload']) ? Str::lower($music['upload']) : null,
            'start_at' => max(0, min((int) config('invitation.music.max_start_at'), (int) ($music['start_at'] ?? 0))),
            'loop' => (bool) ($music['loop'] ?? true),
        ];
    }

    public function update(Invitation $invitation, array $music): void
    {
        $invitation->settings()->updateOrCreate([], ['music' => $this->normalize($music)]);
    }

    /**
     * What the public page plays, or null when music is off or the song is gone.
     * Hidden library tracks keep playing on invitations that already picked them.
     *
     * @return array{url: string, start_at: int, loop: bool}|null
     */
    public function forPage(Invitation $invitation): ?array
    {
        $music = $this->normalize($invitation->settings?->music);

        if (! $music['enabled']) {
            return null;
        }

        $url = $music['source'] === 'upload'
            ? $this->upload($invitation, $music['upload'])?->url()
            : MusicTrack::query()->where('ulid', $music['track'])->first()?->url();

        return $url === null ? null : ['url' => $url, 'start_at' => $music['start_at'], 'loop' => $music['loop']];
    }

    /**
     * A new random name with the extension of the real format, so an M4A named ".mp3" is stored as ".m4a".
     */
    private function fileName(UploadedFile $file): string
    {
        return Str::lower((string) Str::ulid()).'.'.$this->format($file);
    }

    /**
     * Callers validate with audioRules() first; "mp3" is only a fallback.
     */
    private function format(UploadedFile $file): string
    {
        return AudioFormat::detect((string) $file->getRealPath(), (string) $file->getMimeType()) ?? 'mp3';
    }

    /**
     * @return Collection<int, Media>
     */
    private function uploads(Invitation $invitation): Collection
    {
        return $invitation->media()->where('collection', self::COLLECTION)->get();
    }
}
