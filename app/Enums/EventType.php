<?php

namespace App\Enums;

enum EventType: string
{
    case Wedding = 'wedding';
    case Engagement = 'engagement';
    case Birthday = 'birthday';
    case Anniversary = 'anniversary';
    case Graduation = 'graduation';
    case BabyShower = 'baby-shower';
    case Corporate = 'corporate';
    case Event = 'event';
    case Other = 'other';

    public function label(): string
    {
        return __(match ($this) {
            self::Wedding => 'Wedding',
            self::Engagement => 'Engagement',
            self::Birthday => 'Birthday',
            self::Anniversary => 'Anniversary',
            self::Graduation => 'Graduation',
            self::BabyShower => 'Baby Shower',
            self::Corporate => 'Corporate',
            self::Event => 'Event',
            self::Other => 'Other',
        });
    }

    public function icon(): string
    {
        return match ($this) {
            self::Wedding => 'fa-solid fa-ring',
            self::Engagement => 'fa-regular fa-gem',
            self::Birthday => 'fa-solid fa-cake-candles',
            self::Anniversary => 'fa-regular fa-heart',
            self::Graduation => 'fa-solid fa-graduation-cap',
            self::BabyShower => 'fa-solid fa-baby',
            self::Corporate => 'fa-solid fa-briefcase',
            self::Event => 'fa-regular fa-calendar',
            self::Other => 'fa-solid fa-star',
        };
    }
}
