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

    /** Cover field title_layout, besides "design" (each design's own way of setting the names). */
    public const LAYOUTS = ['line', 'stacked', 'cascade'];

    /**
     * The layout to apply, or null to keep the design's own.
     */
    public static function layout(mixed $layout): ?string
    {
        return in_array($layout, self::LAYOUTS, true) ? $layout : null;
    }

    /**
     * The two names around the first "&" ("Raka & Sinta"), or null when the title has no such split.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parts(mixed $title): ?array
    {
        $parts = preg_split('/\s*&\s*/u', trim((string) $title), 2);

        return count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '' ? [$parts[0], $parts[1]] : null;
    }

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
