<?php

namespace App\Models;

use Database\Factories\MusicTrackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MusicTrack extends Model
{
    /** @use HasFactory<MusicTrackFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'artist',
        'disk',
        'path',
        'size',
        'is_premium',
        'is_active',
        'sort_order',
    ];

    protected $hidden = [
        'id',
        'disk',
        'path',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Root-relative URL ("/storage/…" or a bundled "/music/…" file).
     */
    public function url(): string
    {
        if ($this->disk === null) {
            return '/'.ltrim($this->path, '/');
        }

        return (string) parse_url(Storage::disk($this->disk)->url($this->path), PHP_URL_PATH);
    }

    public function isBundled(): bool
    {
        return $this->disk === null;
    }

    /**
     * "Title · Artist", or the title alone.
     */
    public function label(): string
    {
        return filled($this->artist) ? "{$this->title} · {$this->artist}" : $this->title;
    }

    /**
     * @param  Builder<MusicTrack>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<MusicTrack>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
