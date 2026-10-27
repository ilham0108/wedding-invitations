<?php

namespace App\Enums;

enum WhatsAppCampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __(match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Processing => 'Sending',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        });
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Scheduled => 'warning',
            self::Processing => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Processing], true);
    }
}
