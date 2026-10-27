<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores invitation images as resized WebP files so public pages never load huge originals.
 * Callers must validate the upload (MIME, size, dimensions) before calling storeImage().
 */
class MediaService
{
    public const MAX_DIMENSION = 1920;

    public const WEBP_QUALITY = 82;

    public const DISK = 'public';

    /**
     * Validation rules for an uploaded invitation image (rules.md section 58).
     *
     * @return array<int, string>
     */
    public static function imageRules(): array
    {
        return ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'];
    }

    public function storeImage(Invitation $invitation, UploadedFile $file, string $collection): Media
    {
        $stored = $this->storeWebp($file, "invitations/{$invitation->ulid}/{$collection}");

        $media = new Media([
            'invitation_id' => $invitation->getKey(),
            'collection' => $collection,
            'disk' => self::DISK,
            'path' => $stored['path'],
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => 'image/webp',
            'size' => $stored['size'],
            'width' => $stored['width'],
            'height' => $stored['height'],
        ]);
        $media->user()->associate($invitation->user_id);
        $media->save();

        return $media;
    }

    /**
     * Resize to MAX_DIMENSION, keep transparency, and save as WebP under $directory on the public disk.
     *
     * @return array{path: string, size: int, width: int, height: int}
     */
    public function storeWebp(UploadedFile $file, string $directory): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('The uploaded file is not a readable image.');
        }

        $source = $this->applyExifOrientation($source, $file);
        [$width, $height] = $this->fitWithin(imagesx($source), imagesy($source));

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        imagewebp($canvas, null, self::WEBP_QUALITY);
        $webp = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        $path = trim($directory, '/').'/'.Str::lower((string) Str::ulid()).'.webp';
        Storage::disk(self::DISK)->put($path, $webp, 'public');

        return ['path' => $path, 'size' => strlen($webp), 'width' => $width, 'height' => $height];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fitWithin(int $width, int $height): array
    {
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));

        return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
    }

    /**
     * Phone photos are often stored sideways with an EXIF rotation flag.
     */
    private function applyExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;

        $rotated = match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }
}
