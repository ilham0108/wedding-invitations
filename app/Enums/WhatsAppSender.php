<?php

namespace App\Enums;

/**
 * Which WhatsApp number a campaign is sent from.
 */
enum WhatsAppSender: string
{
    case Platform = 'platform';
    case Own = 'own';

    public function label(): string
    {
        return __(match ($this) {
            self::Platform => 'Platform number',
            self::Own => 'My WhatsApp number',
        });
    }

    /**
     * Only platform messages count toward the monthly quota; clients pay their own provider.
     */
    public function countsTowardQuota(): bool
    {
        return $this === self::Platform;
    }
}
