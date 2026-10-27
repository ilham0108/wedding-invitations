<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * App logo uploaded at /admin/settings, stored as a resized WebP (transparency kept) on the public disk.
 * The setting "logo" holds its path. Without an upload, the file from config('branding.logo') is used.
 */
class LogoService
{
    private const DIRECTORY = 'branding/logo';

    public function __construct(private SystemSettings $settings, private AuditLogger $auditLogger, private MediaService $media) {}

    /**
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_height=40,max_width=4000,max_height=4000'];
    }

    /**
     * Replace the logo with $file (already validated with rules()), or remove it when $file is null.
     */
    public function replace(?UploadedFile $file, User $admin): void
    {
        $previous = $this->path();
        $path = $file === null ? '' : $this->media->storeWebp($file, self::DIRECTORY)['path'];

        $this->settings->save(['logo' => $path], $admin, $this->auditLogger);

        if ($previous !== null) {
            Storage::disk(MediaService::DISK)->delete($previous);
        }
    }

    public function exists(): bool
    {
        return $this->path() !== null;
    }

    /**
     * The uploaded logo, else the bundled file from config/branding.php, else null (show the app name).
     */
    public function url(): ?string
    {
        $path = $this->path();

        if ($path !== null) {
            return Storage::disk(MediaService::DISK)->url($path);
        }

        $bundled = ltrim((string) config('branding.logo'), '/');

        return $bundled !== '' && is_file(public_path($bundled))
            ? asset($bundled).'?v='.filemtime(public_path($bundled))
            : null;
    }

    private function path(): ?string
    {
        $path = (string) $this->settings->get('logo');

        return str_starts_with($path, self::DIRECTORY.'/') ? $path : null;
    }
}
