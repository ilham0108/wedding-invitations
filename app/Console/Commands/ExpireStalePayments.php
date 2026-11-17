<?php

namespace App\Console\Commands;

use App\Services\Payment\PaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:expire-stale')]
#[Description('Re-check unpaid gateway orders past their payment window, then activate or expire them')]
class ExpireStalePayments extends Command
{
    public function handle(PaymentService $payments): int
    {
        ['expired' => $expired, 'paid' => $paid] = $payments->expireStale();

        $this->components->info("Expired {$expired}, activated {$paid} order(s).");

        return self::SUCCESS;
    }
}
