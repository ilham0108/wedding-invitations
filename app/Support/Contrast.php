<?php

namespace App\Support;

/**
 * WCAG 2 contrast ratio of two "#rrggbb" colors (1 to 21).
 */
final class Contrast
{
    /** Minimum ratios (WCAG AA): normal text, and large text such as headings. */
    public const TEXT = 4.5;

    public const LARGE_TEXT = 3.0;

    public static function ratio(string $foreground, string $background): float
    {
        [$lighter, $darker] = [self::luminance($foreground), self::luminance($background)];

        if ($lighter < $darker) {
            [$lighter, $darker] = [$darker, $lighter];
        }

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(function (int $value): float {
            $value /= 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, sscanf($hex, '#%02x%02x%02x'));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
