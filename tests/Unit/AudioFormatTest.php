<?php

namespace Tests\Unit;

use App\Support\AudioFormat;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\MakesAudioFiles;

class AudioFormatTest extends TestCase
{
    use MakesAudioFiles;

    private function detect(string $contents, string $mimeType): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, $contents);

        try {
            return AudioFormat::detect($path, $mimeType);
        } finally {
            unlink($path);
        }
    }

    public function test_mpeg_audio_is_mp3(): void
    {
        $this->assertSame('mp3', $this->detect('anything', 'audio/mpeg'));
    }

    public function test_audio_only_mp4_is_m4a_even_when_reported_as_video(): void
    {
        $this->assertSame('m4a', $this->detect($this->mp4Bytes(['soun']), 'video/mp4'));
        $this->assertSame('m4a', $this->detect($this->mp4Bytes(['soun']), 'audio/mp4'));
    }

    public function test_mp4_with_a_video_track_is_rejected(): void
    {
        $this->assertNull($this->detect($this->mp4Bytes(['vide', 'soun']), 'video/mp4'));
        $this->assertNull($this->detect($this->mp4Bytes([]), 'video/mp4'));
    }

    public function test_other_files_and_broken_boxes_are_rejected(): void
    {
        $this->assertNull($this->detect('<?php echo 1;', 'text/x-php'));
        $this->assertNull($this->detect("\x00\x00\xFF\xFFmoov", 'video/mp4'));
        $this->assertSame('audio/mp4', AudioFormat::mimeType('m4a'));
    }

    public function test_an_mp3_behind_a_large_id3_tag_is_mp3_whatever_type_is_reported(): void
    {
        // YouTube converters put the video thumbnail into the tag, so the first 64 KB Livewire sniffs are all tag.
        $this->assertSame('mp3', $this->detect($this->id3Tag(120 * 1024).$this->mpegFrames(20), 'application/octet-stream'));
        $this->assertSame('mp3', $this->detect($this->mpegFrames(20), 'application/octet-stream'));
    }

    public function test_reported_as_unknown_other_files_are_rejected_even_with_an_id3_header(): void
    {
        $this->assertNull($this->detect('<?php echo 1; ?>'.str_repeat('A', 5000), 'application/octet-stream'));
        $this->assertNull($this->detect($this->id3Tag(1000).random_bytes(20000), 'application/octet-stream'));
        // A single frame header without a second one where the first one ends is not enough.
        $this->assertNull($this->detect("\xFF\xFB\x90\x00".str_repeat("\x00", 3000), 'application/octet-stream'));
    }

    public function test_an_mp4_container_is_found_by_its_ftyp_box_when_the_type_is_unknown(): void
    {
        $box = fn (string $type, string $body): string => pack('N', 8 + strlen($body)).$type.$body;
        $hdlr = $box('hdlr', "\0\0\0\0\0\0\0\0soun".str_repeat("\0", 12));

        $this->assertSame('m4a', $this->detect($box('ftyp', 'dash'.str_repeat("\0", 8)).$box('moov', $box('trak', $box('mdia', $hdlr))), 'application/octet-stream'));
    }

    /**
     * An ID3v2.3 tag of $size bytes (syncsafe size) filled with padding.
     */
    private function id3Tag(int $size): string
    {
        $syncsafe = chr(($size >> 21) & 0x7F).chr(($size >> 14) & 0x7F).chr(($size >> 7) & 0x7F).chr($size & 0x7F);

        return "ID3\x03\x00\x00".$syncsafe.str_repeat("\x00", $size);
    }

    /**
     * MPEG-1 Layer III frames at 128 kbit/s and 44.1 kHz without padding: 417 bytes each.
     */
    private function mpegFrames(int $count): string
    {
        return str_repeat("\xFF\xFB\x90\x00".str_repeat("\x55", 413), $count);
    }
}
