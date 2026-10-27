<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\InvitationTemplate;
use App\Models\TemplateCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached read model of published templates and their categories (rules.md section 75).
 * Template and category models flush this cache whenever they change.
 *
 * Only raw attribute arrays are cached and models are hydrated on read: the cache
 * store refuses to unserialize PHP objects (config cache.serializable_classes = false).
 */
class TemplateCatalog
{
    private const TEMPLATES_KEY = 'catalog.templates.v3';

    private const CATEGORIES_KEY = 'catalog.categories.v3';

    /**
     * @return Collection<int, InvitationTemplate>
     */
    public function publishedTemplates(): Collection
    {
        $rows = Cache::rememberForever(self::TEMPLATES_KEY, fn (): array => InvitationTemplate::query()
            ->published()
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true))
            ->with('category:id,name,slug,event_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (InvitationTemplate $template): array => [
                'template' => $template->getAttributes(),
                'category' => $template->category->getAttributes(),
            ])
            ->all());

        $templates = InvitationTemplate::hydrate(array_column($rows, 'template'));
        $categories = TemplateCategory::hydrate(array_column($rows, 'category'));

        return $templates->each(fn (InvitationTemplate $template, int $index) => $template->setRelation('category', $categories[$index]));
    }

    /**
     * Active categories that contain at least one published template.
     *
     * @return Collection<int, TemplateCategory>
     */
    public function categories(): Collection
    {
        $rows = Cache::rememberForever(self::CATEGORIES_KEY, fn (): array => TemplateCategory::query()
            ->active()
            ->whereHas('templates', fn (Builder $query) => $query->published())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon'])
            ->map(fn (TemplateCategory $category): array => $category->getAttributes())
            ->all());

        return TemplateCategory::hydrate($rows);
    }

    /**
     * @return Collection<int, InvitationTemplate>
     */
    public function forEventType(EventType $eventType): Collection
    {
        return $this->publishedTemplates()
            ->filter(fn (InvitationTemplate $template): bool => $template->category->event_type === $eventType)
            ->values();
    }

    public function findPublished(string $slug): ?InvitationTemplate
    {
        return $this->publishedTemplates()->firstWhere('slug', $slug);
    }

    public static function flush(): void
    {
        Cache::forget(self::TEMPLATES_KEY);
        Cache::forget(self::CATEGORIES_KEY);
        Cache::forget('catalog.templates.v2');
        Cache::forget('catalog.categories.v2');
    }
}
