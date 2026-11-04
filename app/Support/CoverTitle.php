<?php

namespace App\Support;

/**
 * Vertical place of the cover title (cover fields title_position / title_offset). Every design's cover
 * root carries data-inv-cover; invitation.css ("Cover title") moves the title block from these values.
 */
final class CoverTitle
{
    public const POSITIONS = ['top', 'center', 'bottom'];

    public const MAX_OFFSET = 30;

    /**
     * The position to apply, or null to keep the design's own.
     */
    public static function position(mixed $position): ?string
    {
        return in_array($position, self::POSITIONS, true) ? $position : null;
    }

    /**
     * The nudge as a CSS length in % of the screen height ("-12svh"), or null for none.
     */
    public static function offset(mixed $offset): ?string
    {
        $offset = is_numeric($offset) ? (int) round(max(-self::MAX_OFFSET, min(self::MAX_OFFSET, (float) $offset))) : 0;

        return $offset === 0 ? null : "{$offset}svh";
    }

    /**
     * data-inv-cover plus the position attribute, for a cover root element.
     */
    public static function attributes(mixed $position): string
    {
        $position = self::position($position);

        return 'data-inv-cover'.($position === null ? '' : ' data-inv-title-position="'.$position.'"');
    }

    /**
     * Inline style declaration for the nudge, or an empty string.
     */
    public static function style(mixed $offset): string
    {
        $offset = self::offset($offset);

        return $offset === null ? '' : "--inv-title-offset: {$offset};";
    }
}
