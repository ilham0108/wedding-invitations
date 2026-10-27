<?php

namespace App\Support;

/**
 * Turns "*word*" in admin-written headlines into a highlighted <em>. The text is escaped first,
 * so only the <em> wrapper is real HTML.
 */
final class Emphasis
{
    public static function html(?string $text, string $class = 'mk-em'): string
    {
        $escaped = e((string) $text);

        return (string) preg_replace('/\*([^*\n]{1,80})\*/', '<em class="'.e($class).'">$1</em>', $escaped);
    }

    /**
     * The same text without the asterisks, for titles and attributes.
     */
    public static function plain(?string $text): string
    {
        return str_replace('*', '', (string) $text);
    }
}
