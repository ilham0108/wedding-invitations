<?php

namespace App\Models;

use App\Services\FontRegistry;
use App\Services\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One uploaded file of a Font: a weight and style in one format (woff2, woff, truetype, opentype).
 */
class FontFile extends Model
{
    protected $fillable = [
        'path',
        'format',
        'weight',
        'style',
        'size',
    ];

    protected $hidden = [
        'id',
        'font_id',
        'path',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => FontRegistry::flush());
        static::deleted(fn () => FontRegistry::flush());
    }

    /**
     * @return BelongsTo<Font, $this>
     */
    public function font(): BelongsTo
    {
        return $this->belongsTo(Font::class);
    }

    /**
     * Root-relative URL of the file on the public disk.
     */
    public function url(): string
    {
        return (string) parse_url(Storage::disk(MediaService::DISK)->url($this->path), PHP_URL_PATH);
    }

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'size' => 'integer',
        ];
    }
}
