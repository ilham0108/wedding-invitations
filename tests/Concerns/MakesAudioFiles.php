<?php

namespace Tests\Concerns;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;

/**
 * Tiny audio files whose contents pass AudioFormat: an MPEG frame, or an MP4 box tree.
 */
trait MakesAudioFiles
{
    protected function mp3(string $name = 'song.mp3'): File
    {
        return UploadedFile::fake()->createWithContent($name, "ID3\x03\x00\x00\x00\x00\x00\x00\xFF\xFB\x90\x00".str_repeat("\x00", 400));
    }

    /**
     * An MP4 file whose tracks have the given handler types ("soun", "vide"), reported as video/mp4
     * like YouTube audio is. In tests Livewire uses the reported MIME type; in production it sniffs it.
     */
    protected function m4a(string $name = 'song.mp3', array $handlers = ['soun']): File
    {
        return UploadedFile::fake()->createWithContent($name, $this->mp4Bytes($handlers))->mimeType('video/mp4');
    }

    protected function mp4Bytes(array $handlers): string
    {
        $box = fn (string $type, string $payload): string => pack('N', 8 + strlen($payload)).$type.$payload;

        $tracks = implode('', array_map(
            fn (string $handler): string => $box('trak', $box('tkhd', str_repeat("\x00", 20)).$box('mdia', $box('hdlr', "\x00\x00\x00\x00\x00\x00\x00\x00".$handler.str_repeat("\x00", 13)))),
            $handlers,
        ));

        return $box('ftyp', 'dash'."\x00\x00\x00\x00".'iso6mp41')
            .$box('moov', $box('mvhd', str_repeat("\x00", 20)).$box('meta', $box('hdlr', "\x00\x00\x00\x00\x00\x00\x00\x00".'mdir'.str_repeat("\x00", 13))).$tracks)
            .$box('mdat', str_repeat("\x00", 256).'vide');
    }
}
