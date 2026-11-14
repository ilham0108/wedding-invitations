<?php

namespace App\Support;

/**
 * Tells which background-music format a file really is, from its contents rather than its name:
 * "mp3" (MPEG audio) or "m4a" (an MP4 container with audio tracks only, e.g. AAC from YouTube,
 * which is often handed out with a ".mp3" name). Anything else, including MP4 files with a
 * video track, is not accepted.
 *
 * The reported MIME type is only a hint. Livewire sniffs a temporary upload from its first 64 KB, and an
 * MP3 from a YouTube converter often starts with a larger ID3 tag (the video thumbnail as cover art), so
 * it is reported as "application/octet-stream". The file itself is read instead: past the ID3 tag there
 * must be an MPEG audio frame, and an MP4 container starts with its "ftyp" box.
 */
final class AudioFormat
{
    private const MP3_TYPES = ['audio/mpeg', 'audio/mp3', 'audio/mpeg3', 'audio/x-mpeg-3'];

    private const MP4_TYPES = ['audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/m4a'];

    /** Bitrates in kbit/s by MPEG version ("v2" also covers 2.5), layer bits (3 = I, 2 = II, 1 = III) and index. */
    private const BITRATES = [
        'v1' => [
            3 => [0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
            2 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
            1 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
        ],
        'v2' => [
            3 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
            2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
            1 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
        ],
    ];

    /** How far past the start (or the ID3 tag) the first MPEG frame may sit, for padding and junk. */
    private const FRAME_SCAN_BYTES = 8192;

    /** Container boxes walked on the way to each track's handler ("hdlr"). */
    private const CONTAINERS = ['moov', 'trak', 'mdia'];

    /**
     * @return 'mp3'|'m4a'|null
     */
    public static function detect(string $path, string $mimeType): ?string
    {
        if (in_array($mimeType, self::MP3_TYPES, true) || self::isMpegAudio($path)) {
            return 'mp3';
        }

        if (! in_array($mimeType, self::MP4_TYPES, true) && ! self::startsWithFtyp($path)) {
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
     * An MPEG audio frame header at the start of the file, or right after its ID3v2 tag.
     */
    private static function isMpegAudio(string $path): bool
    {
        $file = @fopen($path, 'rb');

        if ($file === false) {
            return false;
        }

        try {
            $offset = 0;
            $id3 = (string) fread($file, 10);

            // ID3v2: "ID3", version (2), flags (1), size (4 bytes, 7 bits each); a footer adds 10 more.
            if (strlen($id3) === 10 && str_starts_with($id3, 'ID3')) {
                $size = array_reduce(str_split(substr($id3, 6, 4)), fn (int $carry, string $byte): int => ($carry << 7) | (ord($byte) & 0x7F), 0);
                $offset = 10 + $size + ((ord($id3[5]) & 0x10) ? 10 : 0);
            }

            fseek($file, $offset);
            // Room for the scan plus one whole frame after it (the longest MPEG frame is under 3 KB).
            $bytes = (string) fread($file, self::FRAME_SCAN_BYTES + 4096);
        } finally {
            fclose($file);
        }

        // Two frames in a row, the second exactly where the first one's length puts it.
        for ($i = 0, $last = min(strlen($bytes), self::FRAME_SCAN_BYTES) - 4; $i <= $last; $i++) {
            $length = self::frameLength(substr($bytes, $i, 4));

            if ($length !== null && $i + $length + 4 <= strlen($bytes) && self::frameLength(substr($bytes, $i + $length, 4)) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Length in bytes of the MPEG audio frame this header starts, or null when it is no valid header:
     * frame sync (11 bits), a real version and layer, a usable bitrate and a known sample rate.
     */
    private static function frameLength(string $header): ?int
    {
        if (strlen($header) < 4) {
            return null;
        }

        [$b1, $b2] = [ord($header[1]), ord($header[2])];
        $version = ($b1 >> 3) & 0x03;
        $layer = ($b1 >> 1) & 0x03;
        $bitrateIndex = ($b2 >> 4) & 0x0F;
        $rateIndex = ($b2 >> 2) & 0x03;

        if (ord($header[0]) !== 0xFF || ($b1 & 0xE0) !== 0xE0 || $version === 1 || $layer === 0 || $bitrateIndex === 0 || $bitrateIndex === 15 || $rateIndex === 3) {
            return null;
        }

        $mpeg1 = $version === 3;
        $bitrate = self::BITRATES[$mpeg1 ? 'v1' : 'v2'][$layer][$bitrateIndex] * 1000;
        $sampleRate = [44100, 48000, 32000][$rateIndex] >> ($mpeg1 ? 0 : ($version === 2 ? 1 : 2));
        $padding = ($b2 >> 1) & 0x01;

        return match ($layer) {
            3 => (intdiv(12 * $bitrate, $sampleRate) + $padding) * 4,
            1 => intdiv(($mpeg1 ? 144 : 72) * $bitrate, $sampleRate) + $padding,
            default => intdiv(144 * $bitrate, $sampleRate) + $padding,
        };
    }

    private static function startsWithFtyp(string $path): bool
    {
        $head = (string) @file_get_contents($path, false, null, 0, 12);

        return strlen($head) === 12 && substr($head, 4, 4) === 'ftyp';
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
