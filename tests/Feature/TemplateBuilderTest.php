<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Livewire\TemplateSectionContent;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private InvitationTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => [
                ['type' => 'cover', 'variant' => 'centered', 'background' => 'default'],
                ['type' => 'event', 'variant' => 'cards', 'background' => 'default'],
                ['type' => 'closing', 'variant' => 'default', 'background' => 'default'],
            ]],
            'theme' => ['preset' => 'elegant'],
            'default_configuration' => [],
            'sample_content' => ['cover' => ['title' => 'Romeo & Juliet']],
        ]);
    }

    private function builder(): mixed
    {
        return Livewire::actingAs($this->admin)->test(TemplateBuilder::class, ['template' => $this->template]);
    }

    /**
     * @return array<int, string>
     */
    private function storedTypes(): array
    {
        return array_column($this->template->fresh()->schema['sections'], 'type');
    }

    public function test_only_template_admins_can_open_the_builder_and_its_preview(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($this->admin)->get(route('admin.templates.builder', $this->template->ulid))->assertOk()->assertSeeLivewire(TemplateBuilder::class);
        $this->actingAs($this->admin)->get(route('admin.templates.builder.preview', $this->template->ulid))->assertOk()->assertSee('Romeo &amp; Juliet', false);

        $this->actingAs($client)->get(route('admin.templates.builder', $this->template->ulid))->assertForbidden();
        $this->actingAs($client)->get(route('admin.templates.builder.preview', $this->template->ulid))->assertForbidden();
        Livewire::actingAs($client)->test(TemplateBuilder::class, ['template' => $this->template])->assertForbidden();
    }

    public function test_structure_changes_stay_in_the_draft_until_saved(): void
    {
        $builder = $this->builder()
            ->call('addSection', 'gallery')
            ->assertSet('hasChanges', true)
            ->assertDispatched('template-preview-refresh');

        $this->assertSame(['cover', 'event', 'closing'], $this->storedTypes());
        $this->actingAs($this->admin)->get(route('admin.templates.builder.preview', $this->template->ulid))->assertSee('data-section="gallery"', false);

        $builder->call('save')->assertSet('hasChanges', false)->assertDispatched('notify', type: 'success');

        $this->assertSame(['cover', 'event', 'closing', 'gallery'], $this->storedTypes());
        $this->assertDatabaseHas('audit_logs', ['action' => 'template.configured']);
    }

    public function test_sections_can_be_reordered_restyled_duplicated_and_removed(): void
    {
        $builder = $this->builder();
        [$cover, $event, $closing] = array_column($builder->get('sections'), 'key');

        $builder->call('reorder', $closing, 0)
            ->call('setVariant', $event, 'list')
            ->call('setVariant', $event, 'not-a-variant')
            ->call('setBackground', $event, 'muted')
            ->call('duplicateSection', $event)
            ->call('removeSection', $cover)
            ->call('save');

        $this->assertSame([
            ['type' => 'closing', 'variant' => 'default', 'background' => 'default'],
            ['type' => 'event', 'variant' => 'list', 'background' => 'muted'],
            ['type' => 'event', 'variant' => 'list', 'background' => 'muted'],
        ], $this->template->fresh()->schema['sections']);
    }

    public function test_the_last_section_cannot_be_removed(): void
    {
        $this->template->update(['schema' => ['sections' => ['cover']]]);
        $builder = $this->builder();

        $builder->call('removeSection', $builder->get('sections')[0]['key'])->assertDispatched('notify', type: 'warning');

        $this->assertCount(1, $builder->get('sections'));
    }

    public function test_the_section_list_cannot_be_tampered_with_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->builder()->set('sections', [['key' => 'x', 'type' => 'evil', 'variant' => 'x', 'background' => 'x']]);
    }

    public function test_theme_presets_and_colors_are_validated_and_saved(): void
    {
        $this->builder()
            ->call('applyPreset', 'luxury')
            ->set('theme.primary_color', 'not-a-color')
            ->assertHasErrors(['theme.primary_color'])
            ->set('theme.primary_color', '#123456')
            ->assertHasNoErrors()
            ->call('save');

        $theme = $this->template->fresh()->theme;

        $this->assertSame('luxury', $theme['preset']);
        $this->assertSame('#123456', $theme['primary_color']);
        $this->assertSame('Cinzel', $theme['font_heading']);
    }

    public function test_default_content_stores_only_values_that_differ_from_the_section_defaults(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TemplateSectionContent::class, ['templateUlid' => $this->template->ulid, 'type' => 'closing', 'group' => 'default_configuration'])
            ->set('config.heading', 'Terima Kasih')
            ->assertHasNoErrors()
            ->assertDispatched('template-draft-updated');

        $this->builder()->assertSet('hasChanges', true)->call('save');

        $this->assertSame(['closing' => ['heading' => 'Terima Kasih']], $this->template->fresh()->default_configuration);
    }

    public function test_sample_content_is_edited_on_top_of_the_defaults_and_only_shown_with_samples(): void
    {
        $this->template->update(['default_configuration' => ['closing' => ['heading' => 'Terima Kasih']]]);

        Livewire::actingAs($this->admin)
            ->test(TemplateSectionContent::class, ['templateUlid' => $this->template->ulid, 'type' => 'closing', 'group' => 'sample_content'])
            ->assertSet('config.heading', 'Terima Kasih')
            ->set('config.message', 'Sampai jumpa di hari bahagia kami.')
            ->set('config.date_text', 'ignored unknown field')
            ->assertHasNoErrors();

        $preview = fn (string $content) => $this->actingAs($this->admin)->get(route('admin.templates.builder.preview', [$this->template->ulid, 'content' => $content]));

        $preview('sample_content')->assertSee('Sampai jumpa di hari bahagia kami.')->assertSee('Romeo &amp; Juliet', false);
        $preview('default_configuration')->assertDontSee('Sampai jumpa di hari bahagia kami.')->assertDontSee('Romeo &amp; Juliet', false)->assertSee('Terima Kasih');
    }

    public function test_content_fields_are_validated(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TemplateSectionContent::class, ['templateUlid' => $this->template->ulid, 'type' => 'event', 'group' => 'sample_content'])
            ->call('addItem', 'events')
            ->set('config.events.0.date', '12/06/2027')
            ->assertHasErrors(['config.events.0.date']);

        $this->builder()->assertSet('hasChanges', true);
    }

    public function test_content_images_are_stored_with_the_template(): void
    {
        Storage::fake('public');

        $component = Livewire::actingAs($this->admin)
            ->test(TemplateSectionContent::class, ['templateUlid' => $this->template->ulid, 'type' => 'cover', 'group' => 'sample_content'])
            ->set('uploads.background_image', UploadedFile::fake()->image('cover.jpg', 1200, 800))
            ->assertHasNoErrors();

        $url = $component->get('config.background_image');

        $this->assertStringStartsWith("/storage/templates/{$this->template->ulid}/", $url);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
    }

    public function test_discard_restores_the_saved_template(): void
    {
        $this->builder()->call('addSection', 'gallery')->call('discard')
            ->assertSet('hasChanges', false)
            ->assertCount('sections', 3);

        $this->builder()->assertSet('hasChanges', false)->assertCount('sections', 3);
    }

    public function test_drafts_are_private_to_each_admin(): void
    {
        $this->builder()->call('addSection', 'gallery');

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $this->template])
            ->assertSet('hasChanges', false)
            ->assertCount('sections', 3);
    }

    public function test_saved_changes_apply_to_new_invitations_only(): void
    {
        $service = app(InvitationService::class);
        $data = ['title' => 'John & Jane', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta'];
        $before = $service->createFromTemplate(User::factory()->client()->create(), $this->template, $data);

        $this->builder()->call('addSection', 'gallery')->call('applyPreset', 'luxury')->call('save');

        $after = $service->createFromTemplate(User::factory()->client()->create(), $this->template->fresh(), $data);

        $this->assertSame(['cover', 'event', 'closing'], $before->sections()->pluck('type')->all());
        $this->assertSame(['preset' => 'elegant'], $before->settings->template_theme);
        $this->assertSame(['cover', 'event', 'closing', 'gallery'], $after->sections()->pluck('type')->all());
        $this->assertSame('luxury', $after->settings->template_theme['preset']);
    }

    public function test_the_preview_follows_the_content_tab_and_the_selected_section(): void
    {
        $builder = $this->builder();
        [, $event] = array_column($builder->get('sections'), 'key');

        // The iframe listens for these browser events; $wire.$watch does not fire for $set() in Livewire 4.
        $builder->set('content', 'sample_content')
            ->assertDispatched('template-preview-refresh')
            ->call('select', $event)
            ->assertDispatched('builder-section-selected', anchor: 'preview-1');

        $builder->set('content', 'not-a-group')->assertSet('content', 'default_configuration');
    }
}
