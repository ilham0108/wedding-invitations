<?php

namespace App\Enums;

enum WhatsAppRecipientStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return __(match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Sending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
        });
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Processing => 'warning',
            self::Sent => 'warning',
            self::Delivered => 'success',
            self::Failed => 'danger',
        };
    }

    /**
     * Statuses that still have work to do.
     *
     * @return array<int, self>
     */
    public static function unfinished(): array
    {
        return [self::Pending, self::Processing];
    }

    /**
     * Statuses that count as a successfully sent message (and toward usage).
     *
     * @return array<int, self>
     */
    public static function successful(): array
    {
        return [self::Sent, self::Delivered];
    }
}
