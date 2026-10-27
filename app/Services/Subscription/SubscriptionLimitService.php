<?php

namespace App\Services\Subscription;

use App\Models\Illustration;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\MusicTrack;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppUsageService;

/**
 * Single place that answers "may this user do X?" (rules.md section 48).
 * Feature values come from the user's current subscription (frozen at purchase) or the free plan.
 * Super admins are never limited. Registered as a scoped singleton, so results are memoized per request.
 */
class SubscriptionLimitService
{
    /** @var array<int, array<string, int|bool|null>> */
    private array $features = [];

    /** @var array<int, ?Subscription> */
    private array $subscriptions = [];

    private ?Plan $freePlan = null;

    private bool $freePlanLoaded = false;

    public function currentSubscription(User $user): ?Subscription
    {
        if (! array_key_exists($user->id, $this->subscriptions)) {
            $this->subscriptions[$user->id] = $user->subscriptions()
                ->current()
                ->with(['items', 'plan'])
                ->orderByDesc('ends_at')
                ->first();
        }

        return $this->subscriptions[$user->id];
    }

    /**
     * Drop memoized values after the user's subscription changed in this request.
     */
    public function forget(User $user): void
    {
        unset($this->features[$user->id], $this->subscriptions[$user->id]);
    }

    public function freePlan(): ?Plan
    {
        if (! $this->freePlanLoaded) {
            $this->freePlan = Plan::query()->where('is_free', true)->orderBy('rank')->first();
            $this->freePlanLoaded = true;
        }

        return $this->freePlan;
    }

    /**
     * The plan whose limits apply right now (null only when no free plan exists in the database).
     */
    public function plan(User $user): ?Plan
    {
        return $this->currentSubscription($user)?->plan ?? $this->freePlan();
    }

    /**
     * @return array<string, int|bool|null>
     */
    public function features(User $user): array
    {
        if (! array_key_exists($user->id, $this->features)) {
            $catalog = array_map(fn (array $definition): mixed => $definition['default'], config('plans.features'));

            $this->features[$user->id] = match (true) {
                $user->isSuperAdmin() => array_map(fn (mixed $default): mixed => is_bool($default) ? true : null, $catalog),
                $this->currentSubscription($user) !== null => [...$catalog, ...$this->currentSubscription($user)->features()],
                $this->freePlan() !== null => $this->freePlan()->resolvedFeatures(),
                default => $catalog,
            };
        }

        return $this->features[$user->id];
    }

    /**
     * Numeric limit; null means unlimited.
     */
    public function limit(User $user, string $feature): ?int
    {
        $value = $this->features($user)[$feature] ?? null;

        return is_int($value) ? max(0, $value) : null;
    }

    public function enabled(User $user, string $feature): bool
    {
        return ($this->features($user)[$feature] ?? false) === true;
    }

    public function canCreateInvitation(User $user): bool
    {
        $limit = $this->limit($user, 'max_invitations');

        return $limit === null || $user->invitations()->count() < $limit;
    }

    public function remainingGuests(Invitation $invitation): ?int
    {
        $limit = $this->limit($this->owner($invitation), 'max_guests');

        return $limit === null ? null : max(0, $limit - $invitation->guests()->count());
    }

    public function canAddGuest(Invitation $invitation, int $count = 1): bool
    {
        $remaining = $this->remainingGuests($invitation);

        return $remaining === null || $count <= $remaining;
    }

    /**
     * Platform-number messages only; a client's own number is never limited by the plan.
     */
    public function canSendWhatsApp(User $user, int $count = 1): bool
    {
        return app(WhatsAppUsageService::class)->canSend($user, $count);
    }

    public function canUseTemplate(User $user, InvitationTemplate $template): bool
    {
        return ! $template->is_premium || $this->enabled($user, 'premium_templates');
    }

    public function canUseIllustration(User $user, Illustration $illustration): bool
    {
        return ! $illustration->is_premium || $this->enabled($user, 'premium_illustrations');
    }

    public function canUseCustomDomain(User $user): bool
    {
        return $this->enabled($user, 'custom_domain');
    }

    public function canUseMusicTrack(User $user, MusicTrack $track): bool
    {
        return ! $track->is_premium || $this->enabled($user, 'premium_music');
    }

    public function canUploadMusic(User $user): bool
    {
        return $this->enabled($user, 'custom_music');
    }

    /**
     * Whether the owner may change colors and fonts in the editor's Theme panel.
     */
    public function canCustomizeTheme(User $user): bool
    {
        return $this->enabled($user, 'theme_customization');
    }

    public function showsBranding(User $user): bool
    {
        return ! $this->enabled($user, 'remove_branding');
    }

    /**
     * Whether guests may open this invitation under the owner's current plan.
     * When a plan ends, the oldest invitations within the new limit stay online;
     * newer ones, and ones on premium templates the plan no longer covers, go offline
     * until the owner renews (the owner's choice, 2026-10-19).
     */
    public function isInvitationActive(Invitation $invitation): bool
    {
        $owner = $this->owner($invitation);

        if ($owner->isSuperAdmin()) {
            return true;
        }

        $invitation->loadMissing('template:id,is_premium');

        if ($invitation->template !== null && ! $this->canUseTemplate($owner, $invitation->template)) {
            return false;
        }

        $limit = $this->limit($owner, 'max_invitations');

        if ($limit === null) {
            return true;
        }

        return $owner->invitations()
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->contains($invitation->id);
    }

    private function owner(Invitation $invitation): User
    {
        return $invitation->relationLoaded('user') ? $invitation->user : $invitation->user()->firstOrFail();
    }
}
