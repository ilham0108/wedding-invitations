<?php

namespace App\Models;

use App\Services\FontRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A font installed by an admin (Bunny Fonts family or uploaded files). Built-in fonts live in
 * config/invitation.php; FontRegistry merges both.
 */
class Font extends Model
{
    use HasUlids;

    public const SOURCE_BUNNY = 'bunny';

    public const SOURCE_UPLOAD = 'upload';

    protected $fillable = [
        'name',
        'category',
        'source',
        'bunny_family',
        'fallback',
        'is_active',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => FontRegistry::flush());
        static::deleted(fn () => FontRegistry::flush());
    }

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
     * @return HasMany<FontFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(FontFile::class)->orderBy('weight')->orderBy('style');
    }

    /**
     * @param  Builder<Font>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
