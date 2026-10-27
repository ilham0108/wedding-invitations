<?php

namespace App\Models;

use App\Enums\IllustrationCategory;
use Database\Factories\IllustrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Illustration extends Model
{
    /** @use HasFactory<IllustrationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'category',
        'name',
        'disk',
        'path',
        'width',
        'height',
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
     * Root-relative URL, the same shape invitation image fields already store ("/storage/…", "/illustrations/…").
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
     * @param  Builder<Illustration>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Illustration>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => IllustrationCategory::class,
            'width' => 'integer',
            'height' => 'integer',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
