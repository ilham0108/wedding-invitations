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
}
