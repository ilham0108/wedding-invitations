<?php

namespace Tests\Feature;

use App\Enums\IllustrationCategory;
use App\Livewire\TemplateBuilder;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Template\TemplateDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Look-only builder changes patch the preview in place (template-preview-patch);
 * structural ones still reload it (template-preview-refresh).
 */
class TemplateBuilderLivePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private InvitationTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'event', 'closing']]]);
    }

    private function builder(): Testable
    {
        return Livewire::actingAs($this->admin)->test(TemplateBuilder::class, ['template' => $this->template]);
    }

    /**
     * A builder whose event section (preview-1) is selected and has one ornament.
     */
    private function builderWithOrnament(): Testable
    {
        $illustration = Illustration::query()->create([
            'category' => IllustrationCategory::Ornament, 'name' => 'Bunga', 'disk' => null, 'path' => 'illustrations/ornament-flower-corner.svg',
            'width' => 400, 'height' => 400, 'is_premium' => false, 'is_active' => true, 'sort_order' => 1,
        ]);
        $builder = $this->builder();

        return $builder->call('select', $builder->get('sections')[1]['key'])->call('addOrnament', $illustration->ulid);
    }

    /**
     * @return array<string, mixed>
     */
    private function draftOrnament(): array
    {
        return app(TemplateDraft::class)->definition($this->template, $this->admin)['schema']['sections'][1]['ornaments'][0];
    }

    public function test_ornament_tweaks_patch_the_preview_instead_of_reloading_it(): void
    {
        $this->builderWithOrnament()
            ->call('updateOrnament', 0, 'width', '48')
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => $params['patch']['kind'] === 'ornament'
                && $params['patch']['owner'] === 'preview-1'
                && $params['patch']['list'] === 'ornaments'
                && $params['patch']['index'] === 0
                && str_contains($params['patch']['style'], 'width: 48%;')
                && str_contains($params['patch']['classes'], 'inv-ornament--sway'))
            ->assertNotDispatched('template-preview-refresh');

        // Another layer means another container in the page: that one reloads.
        $this->builderWithOrnament()
            ->call('updateOrnament', 0, 'layer', 'back')
            ->assertDispatched('template-preview-refresh')
            ->assertNotDispatched('template-preview-patch');
    }

    public function test_dragged_ornaments_are_stored_in_one_step_and_clamped(): void
    {
        $this->builderWithOrnament()
            ->call('placeOrnament', 0, 42.5, 180, 1)
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => str_contains($params['patch']['style'], 'left: 42.5%; top: 150%; width: 2%;'))
            ->assertNotDispatched('template-preview-refresh')
            ->call('placeOrnament', 9, 10, 10, 10);

        $this->assertEquals(['x' => 42.5, 'y' => 150, 'width' => 2], array_intersect_key($this->draftOrnament(), array_flip(['x', 'y', 'width'])));
    }

    public function test_theme_changes_send_the_css_variables_the_page_renders(): void
    {
        $this->builder()
            ->set('theme.primary_color', '#123456')
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => $params['patch']['kind'] === 'theme'
                && str_contains($params['patch']['css'], '--inv-primary: #123456;')
                && str_starts_with($params['patch']['font'], 'https://fonts.bunny.net/css?family=')
                && $params['patch']['button'] !== '')
            ->assertNotDispatched('template-preview-refresh');

        // Invalid values are rejected before anything is stored or sent.
        $this->builder()->set('theme.primary_color', 'red;}')->assertHasErrors('theme.primary_color')->assertNotDispatched('template-preview-patch');
    }

    public function test_body_attribute_decorations_and_section_sizes_patch_in_place(): void
    {
        $this->builder()->call('setDecoration', 'divider', 'dots')
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => $params['patch'] === ['kind' => 'attributes', 'attributes' => ['data-inv-divider' => 'dots']])
            ->assertNotDispatched('template-preview-refresh');

        // Section keys only live inside one builder instance.
        $builder = $this->builder();
        $builder->call('setFontSize', $builder->get('sections')[1]['key'], 'heading_size', 'larger')
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => $params['patch'] === ['kind' => 'section-class', 'owner' => 'preview-1', 'prefix' => 'inv-heading-size--', 'value' => 'larger']);

        // The entrance animation is wired up by invitation.js on load: it still reloads.
        $this->builder()->call('setDecoration', 'animation', 'zoom')->assertDispatched('template-preview-refresh');
    }

    public function test_the_preview_marks_each_ornament_so_the_builder_can_find_it(): void
    {
        $this->builderWithOrnament()->call('updateOrnament', 0, 'x', '20');

        $html = $this->actingAs($this->admin)->get(route('admin.templates.builder.preview', $this->template))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-inv-ornament-owner="preview-1" data-inv-ornament-list="ornaments"/', $html);
        $this->assertStringContainsString('data-inv-ornament-index="0" style="left: 20%;', $html);

        // The builder tells the preview which section's ornaments can be dragged.
        $this->builderWithOrnament()->assertSeeHtml('data-ornament-owner="preview-1" data-ornament-list="ornaments"');
    }
}
