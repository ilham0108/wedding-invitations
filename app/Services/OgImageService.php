<?php

namespace App\Services;

use App\Support\PhotoStyle;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The picture link previews show (og:image) when an invitation or a template preview is shared. Chat apps such as
 * WhatsApp want a landscape JPEG or PNG of about 1200×630 under ~300 KB, while uploads are stored as WebP of any
 * shape, so a local picture is cropped (around its focal point) into a cached JPEG on the public disk.
 *
 * Only files of this installation are read (public/ and the public disk, resolved and checked to stay inside
 * them); a URL of another site is passed through untouched and never fetched.
 */
class OgImageService
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const DIRECTORY = 'og';

    private const MAX_BYTES = 300 * 1024;

    /** Larger sources are skipped instead of decoded, to keep GD's memory bounded. */
    private const MAX_SOURCE_PIXELS = 40_000_000;

    /** Bump when the output changes, so cached files are made again. */
    private const VERSION = 6;

    /** Photos narrower than this (width / height) are shown whole instead of cropped. */
    private const MIN_CROP_RATIO = 1.3;

    /**
     * Root-relative URL of the share picture for $source (a root-relative path or an absolute URL), or the source
     * itself when it cannot be converted (another site, a missing file, an unreadable image).
     */
    public function url(?string $source, mixed $focus = null): ?string
    {
        if ($source === null || $source === '') {
            return null;
        }

        // Chat apps show no SVG previews, and GD cannot draw one: no picture beats a broken one.
        if (strtolower(pathinfo((string) parse_url($source, PHP_URL_PATH), PATHINFO_EXTENSION)) === 'svg') {
            return null;
        }

        $file = $this->localFile($source);

        if ($file === null) {
            return $source;
        }

        $focus = PhotoStyle::focus($focus);
        $target = self::DIRECTORY.'/'.sha1($file.'|'.filemtime($file).'|'.$focus.'|'.self::VERSION).'.jpg';
        $disk = Storage::disk(MediaService::DISK);

        if (! $disk->exists($target)) {
            $jpeg = $this->render($file, $focus);

            if ($jpeg === null) {
                return $source;
            }

            $disk->put($target, $jpeg, 'public');
        }

        return (string) parse_url($disk->url($target), PHP_URL_PATH);
    }

    /**
     * A wide photo: the largest crop of the target's proportions, moved towards the focal point as
     * object-position does.
     */
    private function cropAround(\GdImage $canvas, \GdImage $source, int $width, int $height, ?string $focus): void
    {
        [$focusX, $focusY] = $focus !== null ? array_map(fn (string $value): float => (float) $value / 100, explode(' ', $focus)) : [0.5, 0.5];
        [$targetWidth, $targetHeight] = [imagesx($canvas), imagesy($canvas)];
        $scale = min($width / $targetWidth, $height / $targetHeight);
        $cropWidth = (int) round($targetWidth * $scale);
        $cropHeight = (int) round($targetHeight * $scale);

        imagecopyresampled($canvas, $source, 0, 0, (int) round(($width - $cropWidth) * $focusX), (int) round(($height - $cropHeight) * $focusY), $targetWidth, $targetHeight, $cropWidth, $cropHeight);
    }

    /**
     * A tall photo shown whole in the middle, over a blurred and darkened copy of itself: a wide crop of a
     * portrait would cut faces wherever they sit in the photo.
     */
    private function fitWithBackdrop(\GdImage $canvas, \GdImage $source, int $width, int $height): void
    {
        // Blur: shrink, soften, stretch back and soften again (stretching alone leaves visible blocks); then darken.
        $small = imagecreatetruecolor(150, 79);
        $this->cropAround($small, $source, $width, $height, null);

        for ($i = 0; $i < 4; $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $medium = imagecreatetruecolor(300, 158);
        imagecopyresampled($medium, $small, 0, 0, 0, 0, 300, 158, 150, 79);

        for ($i = 0; $i < 3; $i++) {
            imagefilter($medium, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopyresampled($canvas, $medium, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, 300, 158);

        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, imagecolorallocatealpha($canvas, 0, 0, 0, 70));

        $fitWidth = (int) round($width * self::HEIGHT / $height);
        imagecopyresampled($canvas, $source, intdiv(self::WIDTH - $fitWidth, 2), 0, 0, 0, $fitWidth, self::HEIGHT, $width, $height);
    }

    /**
     * The file behind a URL of this site, or null. Paths are resolved with realpath() and must stay inside
     * public/ (bundled pictures) or the public disk (uploads), so "../" can never reach other files.
     */
    private function localFile(string $source): ?string
    {
        $path = $source;

        if (preg_match('#^https?://#i', $source) === 1) {
            if (strcasecmp((string) parse_url($source, PHP_URL_HOST), (string) parse_url((string) config('app.url'), PHP_URL_HOST)) !== 0) {
                return null;
            }

            $path = (string) parse_url($source, PHP_URL_PATH);
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        $path = rawurldecode($path);
        $storageRoot = realpath(Storage::disk(MediaService::DISK)->path(''));
        $publicRoot = realpath(public_path());

        $candidate = str_starts_with($path, '/storage/') && $storageRoot !== false
            ? realpath($storageRoot.DIRECTORY_SEPARATOR.substr($path, strlen('/storage/')))
            : realpath(public_path(ltrim($path, '/')));

        if ($candidate === false || ! is_file($candidate)) {
            return null;
        }

        foreach (array_filter([$storageRoot, $publicRoot]) as $root) {
            if (str_starts_with($candidate, $root.DIRECTORY_SEPARATOR)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * A WIDTH×HEIGHT JPEG: a wide photo cropped around its focal point ("x% y%"), a tall one shown whole, or null.
     */
    private function render(string $file, ?string $focus): ?string
    {
        try {
            $size = @getimagesize($file);

            if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_SOURCE_PIXELS) {
                return null;
            }

            $source = @imagecreatefromstring((string) file_get_contents($file));

            if ($source === false) {
                return null;
            }

            [$width, $height] = [imagesx($source), imagesy($source)];
            $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
            // Transparent pictures (ornaments, illustrations) get a white background instead of black.
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

            if ($width / $height < self::MIN_CROP_RATIO) {
                $this->fitWithBackdrop($canvas, $source, $width, $height);
            } else {
                $this->cropAround($canvas, $source, $width, $height, $focus);
            }

            foreach ([84, 76, 68, 60] as $quality) {
                ob_start();
                imagejpeg($canvas, null, $quality);
                $jpeg = (string) ob_get_clean();

                if (strlen($jpeg) <= self::MAX_BYTES) {
                    break;
                }
            }

            return $jpeg;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
