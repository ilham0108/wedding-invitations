<?php

namespace App\Support;

/**
 * Tells which background-music format a file really is, from its contents rather than its name:
 * "mp3" (MPEG audio) or "m4a" (an MP4 container with audio tracks only, e.g. AAC from YouTube,
 * which is often handed out with a ".mp3" name). Anything else, including MP4 files with a
 * video track, is not accepted.
 */
final class AudioFormat
{
    private const MP3_TYPES = ['audio/mpeg', 'audio/mp3', 'audio/mpeg3', 'audio/x-mpeg-3'];

    private const MP4_TYPES = ['audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/m4a'];

    /** Container boxes walked on the way to each track's handler ("hdlr"). */
    private const CONTAINERS = ['moov', 'trak', 'mdia'];

    /**
     * @return 'mp3'|'m4a'|null
     */
    public static function detect(string $path, string $mimeType): ?string
    {
        if (in_array($mimeType, self::MP3_TYPES, true)) {
            return 'mp3';
        }

        if (! in_array($mimeType, self::MP4_TYPES, true)) {
            return null;
        }

        $handlers = self::trackHandlers($path);

        return in_array('soun', $handlers, true) && ! in_array('vide', $handlers, true) ? 'm4a' : null;
    }

    public static function mimeType(string $format): string
    {
        return $format === 'm4a' ? 'audio/mp4' : 'audio/mpeg';
    }

    /**
     * Handler types of every track in the file ("soun" = audio, "vide" = video), read from the
     * box structure moov → trak → mdia → hdlr. The media data itself is skipped, never read.
     *
     * @return array<int, string>
     */
    private static function trackHandlers(string $path): array
    {
        $file = @fopen($path, 'rb');

        if ($file === false) {
            return [];
        }

        try {
            $handlers = [];
            self::walk($file, 0, (int) fstat($file)['size'], $handlers);

            return $handlers;
        } finally {
            fclose($file);
        }
    }

    /**
     * @param  resource  $file
     * @param  array<int, string>  $handlers
     */
    private static function walk($file, int $start, int $end, array &$handlers, int $depth = 0): void
    {
        $offset = $start;

        while ($offset + 8 <= $end && $depth < 4) {
            fseek($file, $offset);
            $header = fread($file, 16);

            if ($header === false || strlen($header) < 8) {
                return;
            }

            $size = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);
            $headerSize = 8;

            // Size 1 = 64-bit size follows the type; size 0 = the box runs to the end.
            if ($size === 1 && strlen($header) === 16) {
                $size = (int) unpack('J', substr($header, 8, 8))[1];
                $headerSize = 16;
            } elseif ($size === 0) {
                $size = $end - $offset;
            }

            if ($size < $headerSize || $offset + $size > $end) {
                return;
            }

            if (in_array($type, self::CONTAINERS, true)) {
                self::walk($file, $offset + $headerSize, $offset + $size, $handlers, $depth + 1);
            } elseif ($type === 'hdlr' && $size >= $headerSize + 12) {
                // version/flags (4) + pre_defined (4), then the 4-character handler type.
                fseek($file, $offset + $headerSize + 8);
                $handlers[] = (string) fread($file, 4);
            }

            $offset += $size;
        }
    }
}
