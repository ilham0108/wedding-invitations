<?php

namespace App\Enums;

enum GuestMessageStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Hidden = 'hidden';

    public function label(): string
    {
        return __(match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Hidden => 'Hidden',
        });
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Hidden => 'neutral',
        };
    }
}
