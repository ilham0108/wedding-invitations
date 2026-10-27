<?php

namespace App\Enums;

enum RsvpStatus: string
{
    case Pending = 'pending';
    case Attending = 'attending';
    case NotAttending = 'not_attending';

    public function label(): string
    {
        return __(match ($this) {
            self::Pending => 'Pending',
            self::Attending => 'Attending',
            self::NotAttending => 'Not attending',
        });
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Attending => 'success',
            self::NotAttending => 'danger',
        };
    }
}
