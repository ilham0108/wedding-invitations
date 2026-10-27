<?php

namespace App\Services\WhatsApp\Data;

use Carbon\CarbonImmutable;

/**
 * A provider-reported status change for a sent message.
 */
final readonly class DeliveryUpdate
{
    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const READ = 'read';

    public const FAILED = 'failed';

    public function __construct(
        public string $messageId,
        public string $status,
        public ?string $error = null,
        public ?CarbonImmutable $occurredAt = null,
    ) {}
}
