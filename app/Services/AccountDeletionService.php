<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Guest;
use App\Models\GuestMessage;
use App\Models\Invitation;
use App\Models\InvitationSection;
use App\Models\InvitationSetting;
use App\Models\Media;
use App\Models\Rsvp;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting a client account, by the client or by an admin (the owner's choice, 2026-11-17):
 * invitations, guests and everything guests sent are removed for good, and the email address
 * can sign up again.
 *
 * Orders and payments stay as the record of money received, linked to an anonymized, soft-deleted
 * user row; nothing on that row identifies the person anymore.
 */
class AccountDeletionService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private OgImageService $ogImages,
    ) {}

    /**
     * What will be removed, for the warning shown before the user confirms.
     *
     * @return array{invitations: int, guests: int}
     */
    public function summary(User $user): array
    {
        $invitationIds = Invitation::withTrashed()->where('user_id', $user->getKey())->select('id');

        return [
            'invitations' => Invitation::withTrashed()->where('user_id', $user->getKey())->count(),
            'guests' => Guest::withTrashed()->whereIn('invitation_id', $invitationIds)->count(),
        ];
    }

    /**
     * @param  User|null  $admin  The admin who deleted the account; null when the user deleted it themselves.
     */
    public function delete(User $user, ?User $admin = null): void
    {
        $email = $user->email;
        $summary = $this->summary($user);
        $invitations = Invitation::withTrashed()->where('user_id', $user->getKey())->get(['id', 'ulid']);
        $invitationIds = $invitations->modelKeys();
        $mediaFiles = Media::withTrashed()
            ->where(fn ($query) => $query->where('user_id', $user->getKey())->orWhereIn('invitation_id', $invitationIds))
            ->get(['disk', 'path']);

        $shareImages = $this->shareImages($invitationIds);

        DB::transaction(function () use ($user, $invitationIds): void {
            // Children first: these foreign keys restrict deletion of their parents.
            WhatsAppCampaign::query()
                ->where(fn ($query) => $query->where('user_id', $user->getKey())->orWhereIn('invitation_id', $invitationIds))
                ->delete(); // recipients and their API logs cascade
            Rsvp::query()->whereIn('invitation_id', $invitationIds)->delete();
            GuestMessage::withTrashed()->whereIn('invitation_id', $invitationIds)->forceDelete();
            Guest::withTrashed()->whereIn('invitation_id', $invitationIds)->forceDelete();
            Media::withTrashed()
                ->where(fn ($query) => $query->where('user_id', $user->getKey())->orWhereIn('invitation_id', $invitationIds))
                ->forceDelete();
            Invitation::withTrashed()->whereKey($invitationIds)->forceDelete(); // sections, settings, groups cascade

            $user->whatsappTemplates()->delete();
            $user->whatsappProvider()->delete(); // the client's own WhatsApp credentials
            $user->notifications()->delete();
            $user->subscriptions()->current()->update(['status' => SubscriptionStatus::Cancelled, 'ends_at' => now()]);
            $user->roles()->detach();

            // Frees the email for a new sign-up and leaves nothing personal on the row kept for the orders.
            $user->forceFill([
                'name' => __('Deleted account'),
                'email' => 'deleted-'.$user->ulid.'@deleted.invalid',
                'phone' => null,
                'google_id' => null,
                'password' => null,
                'remember_token' => null,
                'email_verified_at' => null,
            ])->save();
            $user->delete();
        });

        // Files go after the commit: a rolled-back deletion must not lose pictures or songs.
        // Share-picture crops first, while the photos they were cut from still exist.
        foreach ($shareImages as [$source, $focus]) {
            $this->ogImages->forget($source, $focus);
        }

        foreach ($mediaFiles as $file) {
            Storage::disk($file->disk)->delete($file->path);
        }

        foreach ($invitations as $invitation) {
            Storage::disk(MediaService::DISK)->deleteDirectory("invitations/{$invitation->ulid}");
        }

        app(SubscriptionLimitService::class)->forget($user);

        // An admin's deletion keeps the email in the audit log, so admins can tell whom they removed
        // (audit entries are pruned after AuditLog::PRUNE_AFTER_DAYS).
        $admin === null
            ? $this->auditLogger->record('user.self_deleted', $user, $user, $summary)
            : $this->auditLogger->record('user.deleted', $admin, $user, [...$summary, 'email' => $email]);
    }

    /**
     * Pictures the invitations show in chat previews (InvitationRenderer meta image): the custom
     * share picture and each cover photo with its focal point.
     *
     * @param  array<int, int>  $invitationIds
     * @return array<int, array{0: string, 1: mixed}>
     */
    private function shareImages(array $invitationIds): array
    {
        $images = InvitationSetting::query()->whereIn('invitation_id', $invitationIds)->whereNotNull('og_image')
            ->pluck('og_image')
            ->map(fn (string $source): array => [$source, null])
            ->all();

        foreach (InvitationSection::query()->whereIn('invitation_id', $invitationIds)->where('type', 'cover')->get(['configuration']) as $cover) {
            if (filled($cover->configuration['background_image'] ?? null)) {
                $images[] = [$cover->configuration['background_image'], $cover->configuration['background_image_focus'] ?? null];
            }
        }

        return $images;
    }
}
