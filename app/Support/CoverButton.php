<?php

namespace App\Support;

/**
 * Place of the cover's open/scroll button (cover fields button_position / button_align). "text" keeps it right
 * below the cover text, "bottom" pins it to the bottom of the screen; "design" is the design's own place.
 * The alignment "text" follows the cover's own alignment field. invitation.css ("Cover button") applies the classes.
 */
final class CoverButton
{
    public const POSITIONS = ['design', 'text', 'bottom'];

    public const ALIGNS = ['text', 'left', 'center', 'right'];

    /**
     * Whether the button sits at the bottom of the screen. $designBottom: the design's own place is the bottom.
     */
    public static function atBottom(mixed $position, bool $designBottom = false): bool
    {
        return $position === 'bottom' || ($designBottom && $position !== 'text');
    }

    /**
     * Classes for the button element: its side, and whether it is pinned to the bottom.
     */
    public static function classes(mixed $position, mixed $align, mixed $coverAlignment, bool $designBottom = false): string
    {
        $side = in_array($align, self::ALIGNS, true) && $align !== 'text' ? $align : ($coverAlignment === 'left' ? 'left' : 'center');

        return 'inv-cover-button inv-cover-button--'.$side.(self::atBottom($position, $designBottom) ? ' inv-cover-button--bottom' : '');
    }
}
