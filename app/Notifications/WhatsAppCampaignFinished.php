<?php

namespace App\Notifications;

use App\Models\WhatsAppCampaign;
use Illuminate\Notifications\Notification;

/**
 * In-app notification (rules.md section 61: WhatsApp campaign completed). Stored data carries ULIDs only.
 */
class WhatsAppCampaignFinished extends Notification
{
    /**
     * @param  array{sent: int, failed: int}  $stats
     */
    public function __construct(
        public WhatsAppCampaign $campaign,
        public array $stats,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('WhatsApp campaign completed'),
            'message' => __(':name: :sent sent, :failed failed.', [
                'name' => $this->campaign->name,
                'sent' => $this->stats['sent'],
                'failed' => $this->stats['failed'],
            ]),
            'url' => route('dashboard.whatsapp.campaigns.show', $this->campaign),
        ];
    }
}
