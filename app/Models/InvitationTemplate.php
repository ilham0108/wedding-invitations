<?php

namespace App\Models;

use App\Enums\TemplateStatus;
use App\Services\TemplateCatalog;
use Database\Factories\InvitationTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class InvitationTemplate extends Model
{
    /** @use HasFactory<InvitationTemplateFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'template_category_id',
        'name',
        'slug',
        'description',
        'thumbnail',
        'preview_image',
        'schema_version',
        'design',
        'schema',
        'default_configuration',
        'theme',
        'decoration',
        'sample_content',
        'music',
        'is_premium',
        'status',
        'sort_order',
    ];

    protected $hidden = [
        'id',
        'template_category_id',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => TemplateCatalog::flush());
        static::deleted(fn () => TemplateCatalog::flush());
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
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TemplateCategory::class, 'template_category_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @param  Builder<InvitationTemplate>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', TemplateStatus::Published);
    }

    /**
     * Ordered section blueprint from the schema. Entries may be a plain type string
     * or ['type' => 'cover', 'variant' => 'framed', 'background' => 'muted', 'ornaments' => [...]].
     *
     * @return array<int, array{type: string, variant: ?string, background: ?string, ornaments: array<int, array<string, mixed>>, desktop_ornaments: array<int, array<string, mixed>>}>
     */
    public function sectionBlueprint(): array
    {
        return collect($this->schema['sections'] ?? [])
            ->map(fn (array|string $section): array => is_string($section)
                ? ['type' => $section, 'variant' => null, 'background' => null, 'ornaments' => [], 'desktop_ornaments' => []]
                : [
                    'type' => (string) ($section['type'] ?? ''),
                    'variant' => $section['variant'] ?? null,
                    'background' => $section['background'] ?? null,
                    'ornaments' => is_array($section['ornaments'] ?? null) ? $section['ornaments'] : [],
                    'desktop_ornaments' => is_array($section['desktop_ornaments'] ?? null) ? $section['desktop_ornaments'] : [],
                ])
            ->filter(fn (array $section): bool => $section['type'] !== '')
            ->values()
            ->all();
    }

    /**
     * The configuration part of the template, in the shape TemplateSchema validates.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schema_version' => $this->schema_version,
            'design' => $this->design ?? 'classic',
            'schema' => $this->schema,
            'theme' => $this->theme ?? [],
            'decoration' => $this->decoration ?? [],
            'default_configuration' => $this->default_configuration ?? [],
            'sample_content' => $this->sample_content ?? [],
            'music' => $this->music ?? [],
        ];
    }

    /**
     * Root-relative URL of the uploaded thumbnail (stored on the public disk), if any.
     */
    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail ? (string) parse_url(Storage::disk('public')->url($this->thumbnail), PHP_URL_PATH) : null;
    }

    public function isPublished(): bool
    {
        return $this->status === TemplateStatus::Published;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'default_configuration' => 'array',
            'schema_version' => 'integer',
            'theme' => 'array',
            'decoration' => 'array',
            'sample_content' => 'array',
            'music' => 'array',
            'is_premium' => 'boolean',
            'status' => TemplateStatus::class,
        ];
    }
}
