<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppCampaignStatus;
use App\Models\WhatsAppCampaign;
use App\Services\WhatsApp\WhatsAppCampaignService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('whatsapp:dispatch-scheduled')]
#[Description('Queue WhatsApp campaigns whose scheduled time has arrived')]
class DispatchScheduledWhatsAppCampaigns extends Command
{
    public function handle(WhatsAppCampaignService $campaigns): int
    {
        $count = 0;

        WhatsAppCampaign::query()
            ->where('status', WhatsAppCampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->each(function (WhatsAppCampaign $campaign) use ($campaigns, &$count): void {
                $count += (int) $campaigns->dispatch($campaign);
            });

        $this->components->info("Queued {$count} scheduled campaign(s).");

        return self::SUCCESS;
    }
}
