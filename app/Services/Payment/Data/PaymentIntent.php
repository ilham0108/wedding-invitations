<?php

namespace App\Services\Payment\Data;

use Carbon\CarbonImmutable;

final readonly class PaymentIntent
{
    /**
     * @param  array<string, mixed>  $payload  Gateway response without secrets.
     */
    public function __construct(
        public ?string $reference,
        public ?string $checkoutUrl,
        public ?CarbonImmutable $expiresAt,
        public array $payload = [],
    ) {}
}
