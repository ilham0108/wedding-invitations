<?php

namespace App\Support;

/**
 * The greeting above the cover button ("Kepada Yth." and the guest's name), from the cover fields guest_greeting,
 * guest_salutation and guest_fallback. Empty texts fall back to the translated defaults.
 */
final class CoverGuest
{
    /**
     * @param  array<string, mixed>  $config  Cover configuration.
     */
    public static function shown(array $config): bool
    {
        return (bool) ($config['guest_greeting'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function salutation(array $config): string
    {
        return self::text($config['guest_salutation'] ?? null) ?? __('Dear Sir/Madam');
    }

    /**
     * The guest's name, or the name for a link without a guest.
     *
     * @param  array<string, mixed>  $config
     */
    public static function name(array $config, ?string $guestName): string
    {
        return filled($guestName) ? $guestName : (self::text($config['guest_fallback'] ?? null) ?? __('Our honored guest'));
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
