<?php

namespace App\Support;

/**
 * Recognises a font file by its first bytes (its signature), not by its name.
 */
final class FontFormat
{
    /** Format (as written in CSS format()) => file extension. */
    public const EXTENSIONS = ['woff2' => 'woff2', 'woff' => 'woff', 'truetype' => 'ttf', 'opentype' => 'otf'];

    public static function detect(string $path): ?string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        $signature = (string) fread($handle, 4);
        fclose($handle);

        return match ($signature) {
            'wOF2' => 'woff2',
            'wOFF' => 'woff',
            "\x00\x01\x00\x00", 'true' => 'truetype',
            'OTTO' => 'opentype',
            default => null,
        };
    }
}
