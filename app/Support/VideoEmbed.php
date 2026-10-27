<?php

namespace App\Support;

/**
 * Turns a YouTube, Vimeo, or direct video-file URL into something a view can render safely.
 * Unknown or unsafe URLs return a null provider instead of being echoed into an iframe.
 */
final class VideoEmbed
{
    private const FILE_EXTENSIONS = ['mp4', 'webm'];

    /**
     * @return array{provider: ?string, embed_url: ?string, thumbnail: ?string}
     */
    public static function parse(?string $url, bool $autoplay = true): array
    {
        $url = trim((string) $url);
        $autoplayQuery = $autoplay ? '?autoplay=1' : '';

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $matches)) {
            return [
                'provider' => 'youtube',
                'embed_url' => "https://www.youtube-nocookie.com/embed/{$matches[1]}{$autoplayQuery}",
                'thumbnail' => "https://i.ytimg.com/vi/{$matches[1]}/hqdefault.jpg",
            ];
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $matches)) {
            return [
                'provider' => 'vimeo',
                'embed_url' => "https://player.vimeo.com/video/{$matches[1]}{$autoplayQuery}",
                'thumbnail' => null,
            ];
        }

        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $isLocalPath = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $isHttps = parse_url($url, PHP_URL_SCHEME) === 'https';

        if (in_array($extension, self::FILE_EXTENSIONS, true) && ($isLocalPath || $isHttps)) {
            return ['provider' => 'file', 'embed_url' => $url, 'thumbnail' => null];
        }

        return ['provider' => null, 'embed_url' => null, 'thumbnail' => null];
    }
}
