<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Site icon uploaded at /admin/settings. One square source image becomes PNG files for every size
 * browsers ask for (tab, Apple home screen, Android/manifest), stored under a fresh directory so
 * a new upload is never hidden by a cached old icon. The setting "favicon" holds that directory.
 */
class FaviconService
{
    /** Pixel size per purpose: browser tab, apple-touch-icon, web manifest icons. */
    public const SIZES = [32, 180, 192, 512];

    private const DIRECTORY = 'branding/favicon';

    public function __construct(private SystemSettings $settings, private AuditLogger $auditLogger) {}

    /**
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=180,min_height=180,max_width=4000,max_height=4000'];
    }

    /**
     * Replace the icon with $file (already validated with rules()), or remove it when $file is null.
     */
    public function replace(?UploadedFile $file, User $admin): void
    {
        $previous = $this->directory();
        $directory = $file === null ? '' : $this->generate($file);

        $this->settings->save(['favicon' => $directory], $admin, $this->auditLogger);

        if ($previous !== null) {
            Storage::disk(MediaService::DISK)->deleteDirectory($previous);
        }
    }

    public function exists(): bool
    {
        return $this->directory() !== null;
    }

    public function url(int $size): ?string
    {
        $directory = $this->directory();

        return $directory === null ? null : Storage::disk(MediaService::DISK)->url("{$directory}/icon-{$size}.png");
    }

    /**
     * Web app manifest (/site.webmanifest) so Android uses the icon and site name on the home screen.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [
            'name' => (string) config('app.name'),
            'short_name' => (string) config('app.name'),
            'start_url' => '/',
            'display' => 'browser',
            'background_color' => '#ffffff',
            'icons' => $this->exists() ? array_map(fn (int $size): array => [
                'src' => $this->url($size),
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
            ], [192, 512]) : [],
        ];
    }

    private function directory(): ?string
    {
        $directory = (string) $this->settings->get('favicon');

        return str_starts_with($directory, self::DIRECTORY.'/') ? $directory : null;
    }

    /**
     * Center-crop to a square, keep transparency, and write one PNG per size.
     */
    private function generate(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('The uploaded file is not a readable image.');
        }

        $side = min(imagesx($source), imagesy($source));
        $x = intdiv(imagesx($source) - $side, 2);
        $y = intdiv(imagesy($source) - $side, 2);
        $directory = self::DIRECTORY.'/'.Str::lower((string) Str::ulid());

        foreach (self::SIZES as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            imagecopyresampled($canvas, $source, 0, 0, $x, $y, $size, $size, $side, $side);

            ob_start();
            imagepng($canvas, null, 9);
            Storage::disk(MediaService::DISK)->put("{$directory}/icon-{$size}.png", (string) ob_get_clean(), 'public');

            imagedestroy($canvas);
        }

        imagedestroy($source);

        return $directory;
    }
}
