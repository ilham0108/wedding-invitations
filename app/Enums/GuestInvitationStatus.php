<?php

namespace App\Enums;

enum GuestInvitationStatus: string
{
    case NotSent = 'not_sent';
    case Sent = 'sent';
    case Opened = 'opened';

    public function label(): string
    {
        return __(match ($this) {
            self::NotSent => 'Not sent',
            self::Sent => 'Sent',
            self::Opened => 'Opened',
        });
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::NotSent => 'neutral',
            self::Sent => 'warning',
            self::Opened => 'success',
        };
    }
}
