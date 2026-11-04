<?php

namespace App\Support;

/**
 * Couple layout "Photo beside" (couple field photo_side): which side of the name each portrait sits on.
 * invitation.css (".inv-person-beside") places the columns; every design styles the portrait and text itself.
 */
final class PhotoSide
{
    public const SIDES = ['left', 'right', 'alternate'];

    /**
     * Whether the portrait of the person at $index (zero-based) sits on the right. "alternate" starts on the left.
     */
    public static function isRight(mixed $side, int $index): bool
    {
        return match ($side) {
            'right' => true,
            'alternate' => $index % 2 === 1,
            default => false,
        };
    }

    /**
     * Row classes for one person, or an empty string without a photo (the design's own centered layout).
     */
    public static function classes(mixed $side, int $index, bool $hasPhoto): string
    {
        if (! $hasPhoto) {
            return '';
        }

        return 'inv-person-beside'.(self::isRight($side, $index) ? ' inv-person-beside--right' : '');
    }
}
