<?php

namespace Tests\Unit;

use App\Support\VideoEmbed;
use PHPUnit\Framework\TestCase;

class VideoEmbedTest extends TestCase
{
    public function test_youtube_and_vimeo_links_become_privacy_friendly_embeds(): void
    {
        $this->assertSame([
            'provider' => 'youtube',
            'embed_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1',
            'thumbnail' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ], VideoEmbed::parse('https://youtu.be/dQw4w9WgXcQ'));

        $this->assertSame('youtube', VideoEmbed::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10')['provider']);
        $this->assertSame('https://player.vimeo.com/video/76979871', VideoEmbed::parse('https://vimeo.com/76979871', autoplay: false)['embed_url']);
    }

    public function test_local_and_https_video_files_are_supported(): void
    {
        $this->assertSame(['provider' => 'file', 'embed_url' => '/videos/fonnte-tutorial.mp4', 'thumbnail' => null], VideoEmbed::parse('/videos/fonnte-tutorial.mp4'));
        $this->assertSame('file', VideoEmbed::parse('https://cdn.example.com/tutorial.webm')['provider']);
    }

    public function test_unsafe_or_unknown_urls_are_rejected(): void
    {
        foreach ([null, '', 'javascript:alert(1)', 'http://insecure.example.com/video.mp4', '//evil.example.com/video.mp4', '/videos/notes.pdf', 'https://example.com/page'] as $url) {
            $this->assertNull(VideoEmbed::parse($url)['provider'], 'Rejected: '.var_export($url, true));
        }
    }
}
