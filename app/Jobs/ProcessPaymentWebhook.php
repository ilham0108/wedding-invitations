<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * A verified webhook only says "something changed". The job asks the gateway for the
 * authoritative status and applies it (rules.md section 76). While the gateway still
 * reports pending, or cannot be reached, the job retries with backoff.
 */
class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 300];

    public function __construct(public string $paymentUlid) {}

    public function handle(PaymentService $payments): void
    {
        $payment = Payment::query()->where('ulid', $this->paymentUlid)->first();

        if ($payment === null || $payment->status !== PaymentStatus::Pending) {
            return;
        }

        if ($payments->sync($payment)->status === PaymentStatus::Pending) {
            throw new RuntimeException("Payment {$payment->ulid} is still pending at the gateway.");
        }
    }
}
