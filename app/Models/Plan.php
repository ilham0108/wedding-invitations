<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'features',
        'is_free',
        'is_active',
        'is_highlighted',
        'rank',
        'sort_order',
    ];

    protected $hidden = [
        'id',
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

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class)->orderBy('amount');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activePrices(): HasMany
    {
        return $this->prices()->where('is_active', true);
    }

    /**
     * Every catalog feature with this plan's value, falling back to the catalog default.
     *
     * @return array<string, int|bool|null>
     */
    public function resolvedFeatures(): array
    {
        $values = (array) $this->features;
        $resolved = [];

        foreach (config('plans.features') as $key => $definition) {
            $resolved[$key] = array_key_exists($key, $values) ? $values[$key] : $definition['default'];
        }

        return $resolved;
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('rank');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_free' => 'boolean',
            'is_active' => 'boolean',
            'is_highlighted' => 'boolean',
            'rank' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
