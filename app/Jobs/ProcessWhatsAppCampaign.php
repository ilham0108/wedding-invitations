<?php

namespace App\Jobs;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\WhatsAppCampaign;
use App\Services\WhatsApp\WhatsAppDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fans a campaign out into one SendWhatsAppMessage job per pending recipient.
 */
class ProcessWhatsAppCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $campaignUlid)
    {
        $this->onQueue(config('whatsapp.queue'));
    }

    public function handle(WhatsAppDeliveryService $delivery): void
    {
        $campaign = WhatsAppCampaign::query()->where('ulid', $this->campaignUlid)->first();

        if ($campaign === null || $campaign->status !== WhatsAppCampaignStatus::Processing) {
            return;
        }

        $campaign->recipients()
            ->where('status', WhatsAppRecipientStatus::Pending)
            ->select(['id', 'ulid'])
            ->chunkById(500, function ($recipients): void {
                foreach ($recipients as $recipient) {
                    SendWhatsAppMessage::dispatch($recipient->ulid);
                }
            });

        $delivery->finalizeIfDone($campaign);
    }
}
