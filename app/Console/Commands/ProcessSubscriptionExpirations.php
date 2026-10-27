<?php

namespace App\Console\Commands;

use App\Services\Subscription\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:process-expirations')]
#[Description('Remind owners before paid plans end and expire plans that ran out')]
class ProcessSubscriptionExpirations extends Command
{
    public function handle(SubscriptionService $subscriptions): int
    {
        ['reminded' => $reminded, 'expired' => $expired] = $subscriptions->processExpirations();

        $this->components->info("Reminded {$reminded}, expired {$expired} subscription(s).");

        return self::SUCCESS;
    }
}
