<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\TemplateStatus;
use App\Livewire\TemplateCategoryManager;
use App\Livewire\TemplateManager;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\TemplateCategory;
use App\Models\User;
use App\Services\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function weddingCategory(): TemplateCategory
    {
        return TemplateCategory::factory()->create(['slug' => 'wedding', 'name' => 'Wedding']);
    }

    public function test_only_admins_manage_templates_and_categories(): void
    {
        $admin = $this->admin();
        $client = User::factory()->client()->create();

        foreach (['/admin/templates', '/admin/template-categories'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
            $this->actingAs($client)->get($url)->assertForbidden();
        }

        Livewire::actingAs($client)->test(TemplateManager::class)->assertForbidden();
        Livewire::actingAs($client)->test(TemplateCategoryManager::class)->assertForbidden();
    }

    public function test_admin_creates_a_draft_template_with_a_starter_structure(): void
    {
        $category = $this->weddingCategory();

        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('create')
            ->set('form.name', 'Rustic Garden')
            ->assertSet('form.slug', 'rustic-garden')
            ->set('form.category', 'wedding')
            ->set('form.is_premium', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success')
            ->assertSee('Rustic Garden');

        $template = InvitationTemplate::query()->where('slug', 'rustic-garden')->sole();

        $this->assertSame(TemplateStatus::Draft, $template->status);
        $this->assertTrue($template->category->is($category));
        $this->assertTrue($template->is_premium);
        $this->assertSame(['cover', 'couple', 'event', 'rsvp', 'closing'], array_column($template->schema['sections'], 'type'));
        $this->assertSame(1, $template->schema_version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'template.created']);
    }

    public function test_template_form_is_validated(): void
    {
        $this->weddingCategory();
        InvitationTemplate::factory()->create(['slug' => 'taken']);

        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('create')
            ->set('form.name', '')
            ->set('form.slug', 'taken')
            ->set('form.category', 'does-not-exist')
            ->call('save')
            ->assertHasErrors(['form.name', 'form.slug', 'form.category']);
    }

    public function test_admin_edits_metadata_and_uploads_a_thumbnail(): void
    {
        Storage::fake('public');
        $this->weddingCategory();
        $template = InvitationTemplate::factory()->create(['name' => 'Old Name']);

        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('edit', $template->ulid)
            ->assertSet('form.name', 'Old Name')
            ->set('form.name', 'New Name')
            ->set('form.category', 'wedding')
            ->set('thumbnail', UploadedFile::fake()->image('thumb.png', 800, 1000))
            ->call('save')
            ->assertHasNoErrors();

        $template->refresh();

        $this->assertSame('New Name', $template->name);
        $this->assertSame('wedding', $template->category->slug);
        $this->assertStringStartsWith('templates/thumbnails/', $template->thumbnail);
        $this->assertStringEndsWith('.webp', $template->thumbnail);
        Storage::disk('public')->assertExists($template->thumbnail);
        $this->assertStringStartsWith('/storage/templates/thumbnails/', $template->thumbnailUrl());
    }

    public function test_duplicate_creates_an_independent_draft_copy(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('templates/thumbnails/original.webp', 'image');
        $template = InvitationTemplate::factory()->create([
            'name' => 'Elegant',
            'slug' => 'elegant',
            'thumbnail' => 'templates/thumbnails/original.webp',
            'decoration' => ['divider' => 'dots'],
        ]);

        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('duplicate', $template->ulid)
            ->assertDispatched('notify', type: 'success');

        $copy = InvitationTemplate::query()->whereKeyNot($template->getKey())->sole();

        $this->assertSame('elegant-copy', $copy->slug);
        $this->assertNotSame($template->ulid, $copy->ulid);
        $this->assertSame(TemplateStatus::Draft, $copy->status);
        $this->assertSame($template->schema, $copy->schema);
        $this->assertSame(['divider' => 'dots'], $copy->decoration);
        $this->assertNotSame($template->thumbnail, $copy->thumbnail);
        Storage::disk('public')->assertExists($copy->thumbnail);
    }

    public function test_publishing_requires_a_valid_configuration(): void
    {
        $category = $this->weddingCategory();
        $broken = InvitationTemplate::factory()->for($category, 'category')->draft()->create(['schema' => ['sections' => ['not-a-section']]]);
        $valid = InvitationTemplate::factory()->for($category, 'category')->draft()->create();

        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('publish', $broken->ulid)
            ->assertDispatched('notify', type: 'error')
            ->call('publish', $valid->ulid)
            ->assertDispatched('notify', type: 'success');

        $this->assertSame(TemplateStatus::Draft, $broken->fresh()->status);
        $this->assertSame(TemplateStatus::Published, $valid->fresh()->status);
        $this->assertTrue(app(TemplateCatalog::class)->publishedTemplates()->contains('slug', $valid->slug));
    }

    public function test_unpublishing_removes_the_template_from_the_catalog(): void
    {
        $template = InvitationTemplate::factory()->for($this->weddingCategory(), 'category')->create();
        $this->assertTrue(app(TemplateCatalog::class)->publishedTemplates()->contains('slug', $template->slug));

        Livewire::actingAs($this->admin())->test(TemplateManager::class)->call('unpublish', $template->ulid);

        $this->assertSame(TemplateStatus::Draft, $template->fresh()->status);
        $this->assertFalse(app(TemplateCatalog::class)->publishedTemplates()->contains('slug', $template->slug));
    }

    public function test_templates_in_use_are_unpublished_instead_of_deleted(): void
    {
        $unused = InvitationTemplate::factory()->create();
        $used = InvitationTemplate::factory()->create();
        Invitation::factory()->create(['invitation_template_id' => $used->getKey()])->delete();

        Livewire::actingAs($this->admin())
            ->test(TemplateManager::class)
            ->call('remove', $unused->ulid)
            ->assertDispatched('notify', type: 'success')
            ->call('remove', $used->ulid)
            ->assertDispatched('notify', type: 'warning');

        $this->assertSoftDeleted($unused);
        $this->assertNotSoftDeleted($used);
        $this->assertSame(TemplateStatus::Draft, $used->fresh()->status);
    }

    public function test_draft_previews_are_only_visible_to_template_admins(): void
    {
        $template = InvitationTemplate::factory()->draft()->create(['name' => 'Secret Draft']);

        $this->get(route('templates.preview', $template))->assertNotFound();
        $this->actingAs(User::factory()->client()->create())->get(route('templates.preview', $template))->assertNotFound();

        $this->actingAs($this->admin())
            ->get(route('templates.preview', $template))
            ->assertOk()
            ->assertSee('Secret Draft')
            ->assertDontSee(route('dashboard.invitations.create', ['template' => $template->slug]), false);
    }

    public function test_admin_creates_a_category_for_an_event_type(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TemplateCategoryManager::class)
            ->call('create')
            ->set('form.name', 'Islamic Wedding')
            ->assertSet('form.slug', 'islamic-wedding')
            ->set('form.event_type', EventType::Wedding->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Islamic Wedding');

        $category = TemplateCategory::query()->where('slug', 'islamic-wedding')->sole();
        $template = InvitationTemplate::factory()->for($category, 'category')->create();

        $this->assertSame(EventType::Wedding, $category->event_type);
        $this->assertTrue(app(TemplateCatalog::class)->forEventType(EventType::Wedding)->contains('slug', $template->slug));
        $this->assertDatabaseHas('audit_logs', ['action' => 'template-category.created']);
    }

    public function test_category_icon_must_be_a_font_awesome_class(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TemplateCategoryManager::class)
            ->call('create')
            ->set('form.name', 'Test')
            ->set('form.icon', 'fa-solid fa-ring" onclick="alert(1)')
            ->call('save')
            ->assertHasErrors(['form.icon']);
    }

    public function test_categories_with_templates_are_deactivated_instead_of_deleted(): void
    {
        $empty = TemplateCategory::factory()->create();
        $used = TemplateCategory::factory()->create();
        InvitationTemplate::factory()->for($used, 'category')->create();

        Livewire::actingAs($this->admin())
            ->test(TemplateCategoryManager::class)
            ->call('remove', $empty->slug)
            ->assertDispatched('notify', type: 'success')
            ->call('remove', $used->slug)
            ->assertDispatched('notify', type: 'warning');

        $this->assertModelMissing($empty);
        $this->assertFalse($used->fresh()->is_active);
    }
}
