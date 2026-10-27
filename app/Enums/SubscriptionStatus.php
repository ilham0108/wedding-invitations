<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __(match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        });
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expired => 'warning',
            self::Cancelled => 'neutral',
        };
    }
}
