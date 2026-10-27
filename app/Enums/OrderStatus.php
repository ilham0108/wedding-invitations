<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __(match ($this) {
            self::Pending => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Expired => 'Expired',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        });
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Expired, self::Cancelled => 'neutral',
            self::Failed => 'danger',
        };
    }
}
