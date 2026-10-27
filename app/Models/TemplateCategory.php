<?php

namespace App\Models;

use App\Enums\EventType;
use App\Services\TemplateCatalog;
use Database\Factories\TemplateCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateCategory extends Model
{
    /** @use HasFactory<TemplateCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'event_type',
        'description',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => TemplateCatalog::flush());
        static::deleted(fn () => TemplateCatalog::flush());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function templates(): HasMany
    {
        return $this->hasMany(InvitationTemplate::class);
    }

    /**
     * @param  Builder<TemplateCategory>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'is_active' => 'boolean',
        ];
    }
}
