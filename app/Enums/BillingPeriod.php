<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * How a plan price is billed. One-time purchases last duration_days; recurring prices
 * extend by a calendar month or year each time they are paid (renewal is a new payment).
 */
enum BillingPeriod: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __(match ($this) {
            self::OneTime => 'One-time',
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        });
    }

    public function isRecurring(): bool
    {
        return $this !== self::OneTime;
    }

    public function extend(CarbonImmutable $from, ?int $durationDays): CarbonImmutable
    {
        return match ($this) {
            self::OneTime => $from->addDays(max(1, (int) $durationDays)),
            self::Monthly => $from->addMonthNoOverflow(),
            self::Yearly => $from->addYearNoOverflow(),
        };
    }
}
