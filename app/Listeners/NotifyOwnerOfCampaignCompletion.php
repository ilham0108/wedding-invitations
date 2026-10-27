<?php

namespace App\Listeners;

use App\Events\WhatsAppCampaignCompleted;
use App\Notifications\WhatsAppCampaignFinished;
use App\Services\WhatsApp\WhatsAppCampaignService;

class NotifyOwnerOfCampaignCompletion
{
    public function __construct(private WhatsAppCampaignService $campaigns) {}

    public function handle(WhatsAppCampaignCompleted $event): void
    {
        $campaign = $event->campaign->loadMissing('user');

        $campaign->user->notify(new WhatsAppCampaignFinished($campaign, $this->campaigns->stats($campaign)));
    }
}
