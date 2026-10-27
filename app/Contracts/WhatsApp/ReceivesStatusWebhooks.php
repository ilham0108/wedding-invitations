<?php

namespace App\Contracts\WhatsApp;

use App\Services\WhatsApp\Data\DeliveryUpdate;
use Illuminate\Http\Request;

/**
 * Optional capability for providers that push delivery status to a webhook.
 */
interface ReceivesStatusWebhooks
{
    /**
     * Check the request signature with the provider secret. Unsigned requests must be rejected.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * @return array<int, DeliveryUpdate>
     */
    public function parseStatusWebhook(Request $request): array;
}
