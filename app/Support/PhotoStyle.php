<?php

namespace App\Support;

/**
 * Inline CSS for cropped photos: the focal point picked in the editor (image fields with
 * "focus", stored next to them as "{key}_focus" = "x% y%") and a section's photo proportions.
 * Values are validated here, so views can print the result into a style attribute.
 */
final class PhotoStyle
{
    /** Section "photo_ratio" options and their CSS; "default" keeps the design's own proportions. */
    public const RATIOS = [
        'default' => null,
        'original' => 'aspect-ratio: auto; height: auto',
        'landscape' => 'aspect-ratio: 4 / 3',
        'square' => 'aspect-ratio: 1 / 1',
        'portrait' => 'aspect-ratio: 4 / 5',
    ];

    /**
     * The focal point as "x% y%" (whole numbers 0–100), or null when unset or malformed.
     */
    public static function focus(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{1,3})% (\d{1,3})%$/', trim($value), $match) !== 1) {
            return null;
        }

        [$x, $y] = [(int) $match[1], (int) $match[2]];

        return $x <= 100 && $y <= 100 ? "{$x}% {$y}%" : null;
    }

    /**
     * Style for an <img> cropped with object-fit: cover.
     */
    public static function image(mixed $focus, mixed $ratio = null): string
    {
        $focus = self::focus($focus);

        return implode('; ', array_filter([
            $focus !== null ? "object-position: {$focus}" : null,
            self::RATIOS[is_string($ratio) ? $ratio : 'default'] ?? null,
        ]));
    }

    /**
     * Style for an element showing the photo as a cover-sized background image.
     */
    public static function background(mixed $focus): string
    {
        $focus = self::focus($focus);

        return $focus !== null ? "background-position: {$focus}" : '';
    }
}
