<?php

namespace App\Services\Payment\Data;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * Normalized payment state from a gateway (webhook or status query).
 */
final readonly class PaymentNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $externalId,
        public PaymentStatus $status,
        public ?string $reference = null,
        public ?int $paidAmount = null,
        public ?string $method = null,
        public ?CarbonImmutable $paidAt = null,
        public array $payload = [],
    ) {}
}
