<?php

namespace Tests\Feature;

use App\Enums\TemplateStatus;
use App\Models\InvitationTemplate;
use App\Services\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TemplateCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_cache_is_invalidated_when_a_template_changes(): void
    {
        $catalog = app(TemplateCatalog::class);
        $template = InvitationTemplate::factory()->create();

        $this->assertCount(1, $catalog->publishedTemplates());

        $template->update(['status' => TemplateStatus::Draft]);

        $this->assertCount(0, $catalog->publishedTemplates());
        $this->assertCount(0, $catalog->categories());
    }

    /**
     * Regression: a serializing store (database, as in .env) must round-trip the catalog
     * even though cache.serializable_classes forbids unserializing PHP objects.
     */
    public function test_catalog_survives_a_serializing_cache_store(): void
    {
        config(['cache.default' => 'database', 'cache.serializable_classes' => false]);
        Cache::forgetDriver('database');

        $template = InvitationTemplate::factory()->create(['name' => 'Cached Design']);
        $catalog = app(TemplateCatalog::class);

        $catalog->publishedTemplates();
        $catalog->categories();

        $fromCache = $catalog->publishedTemplates();
        $cachedTemplate = $fromCache->first();

        $this->assertInstanceOf(InvitationTemplate::class, $cachedTemplate);
        $this->assertTrue($cachedTemplate->is($template));
        $this->assertSame('Cached Design', $cachedTemplate->name);
        $this->assertSame(TemplateStatus::Published, $cachedTemplate->status);
        $this->assertIsArray($cachedTemplate->schema);
        $this->assertSame($template->category->slug, $cachedTemplate->category->slug);
        $this->assertSame($template->category->slug, $catalog->categories()->first()->slug);
        $this->assertTrue($catalog->findPublished($template->slug)->is($template));
    }

    public function test_inactive_category_hides_its_templates(): void
    {
        $catalog = app(TemplateCatalog::class);
        $template = InvitationTemplate::factory()->create();

        $template->category->update(['is_active' => false]);

        $this->assertCount(0, $catalog->publishedTemplates());
    }
}
