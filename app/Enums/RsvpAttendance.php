<?php

namespace App\Enums;

enum RsvpAttendance: string
{
    case Attending = 'attending';
    case NotAttending = 'not_attending';

    public function label(): string
    {
        return __(match ($this) {
            self::Attending => 'Attending',
            self::NotAttending => 'Not attending',
        });
    }

    public function toRsvpStatus(): RsvpStatus
    {
        return match ($this) {
            self::Attending => RsvpStatus::Attending,
            self::NotAttending => RsvpStatus::NotAttending,
        };
    }
}
