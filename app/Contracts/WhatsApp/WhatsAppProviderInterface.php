<?php

namespace App\Contracts\WhatsApp;

use App\Services\WhatsApp\Data\DeliveryUpdate;
use App\Services\WhatsApp\Data\SendResult;

/**
 * Provider abstraction (rules.md section 39). Phone numbers are digits with country code, e.g. "6281234567890".
 */
interface WhatsAppProviderInterface
{
    public function sendMessage(string $phone, string $message): SendResult;

    /**
     * @param  array<array-key, array{phone: string, message: string}>  $messages
     * @return array<array-key, SendResult> keyed like $messages
     */
    public function sendBulk(array $messages): array;

    /**
     * Latest delivery status, or null when the provider only reports status through webhooks.
     */
    public function getMessageStatus(string $providerMessageId): ?DeliveryUpdate;
}
