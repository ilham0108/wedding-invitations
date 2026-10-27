<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Enums\WhatsAppSender;
use App\Models\User;
use App\Models\WhatsAppCampaignRecipient;
use App\Services\Subscription\SubscriptionLimitService;

/**
 * Message usage per calendar month (rules.md section 46). Only messages sent through the
 * platform number count; a client's own number is billed by their provider.
 *
 * The limit is the plan feature "max_whatsapp_messages" (null = unlimited).
 */
class WhatsAppUsageService
{
    public function __construct(private SubscriptionLimitService $limits) {}

    public function usedThisMonth(User $user): int
    {
        return WhatsAppCampaignRecipient::query()
            ->whereHas('campaign', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('sender', WhatsAppSender::Platform))
            ->whereIn('status', WhatsAppRecipientStatus::successful())
            ->where('sent_at', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Messages already queued but not sent yet; they reserve quota so parallel campaigns cannot overshoot.
     */
    public function reserved(User $user): int
    {
        return WhatsAppCampaignRecipient::query()
            ->whereHas('campaign', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('sender', WhatsAppSender::Platform)
                ->whereIn('status', [WhatsAppCampaignStatus::Scheduled, WhatsAppCampaignStatus::Processing]))
            ->whereIn('status', WhatsAppRecipientStatus::unfinished())
            ->count();
    }

    public function limit(User $user): ?int
    {
        return $this->limits->limit($user, 'max_whatsapp_messages');
    }

    public function remaining(User $user): ?int
    {
        $limit = $this->limit($user);

        return $limit === null ? null : max(0, $limit - $this->usedThisMonth($user) - $this->reserved($user));
    }

    public function canSend(User $user, int $count): bool
    {
        $remaining = $this->remaining($user);

        return $remaining === null || $count <= $remaining;
    }
}
