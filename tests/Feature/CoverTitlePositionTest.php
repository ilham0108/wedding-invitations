<?php

namespace Tests\Feature;

use App\Livewire\TemplateSectionContent;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Support\CoverTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cover fields title_position / title_offset: where the cover title sits, in every design.
 */
class CoverTitlePositionTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'botanical-cream', 'mocha-glass'];

    public function test_covers_keep_the_design_place_until_a_position_is_chosen(): void
    {
        foreach (self::DESIGNS as $design) {
            $html = $this->get(route('templates.preview', InvitationTemplate::factory()->create(['design' => $design])))->assertOk()->getContent();

            $this->assertStringContainsString('data-inv-cover', $html, "[{$design}]");
            $this->assertStringNotContainsString('data-inv-title-position', $html, "[{$design}]");
            $this->assertStringNotContainsString('--inv-title-offset', $html, "[{$design}]");
        }
    }

    public function test_every_design_renders_the_chosen_position_and_nudge(): void
    {
        foreach (self::DESIGNS as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'default_configuration' => ['cover' => ['title_position' => 'top', 'title_offset' => -12]],
            ]);

            $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

            $this->assertStringContainsString('data-inv-title-position="top"', $html, "[{$design}]");
            $this->assertStringContainsString('--inv-title-offset: -12svh;', $html, "[{$design}]");
        }
    }

    public function test_values_are_limited_to_the_known_positions_and_thirty_percent(): void
    {
        $this->assertNull(CoverTitle::position('design'));
        $this->assertNull(CoverTitle::position('"><script>'));
        $this->assertSame('bottom', CoverTitle::position('bottom'));
        $this->assertSame('30svh', CoverTitle::offset(80));
        $this->assertSame('-30svh', CoverTitle::offset('-45'));
        $this->assertNull(CoverTitle::offset(0));
        $this->assertNull(CoverTitle::offset('abc'));
    }

    public function test_the_builder_moves_the_title_in_place_and_reloads_for_other_fields(): void
    {
        $template = InvitationTemplate::factory()->create();
        $editor = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateSectionContent::class, ['templateUlid' => $template->ulid, 'type' => 'cover', 'group' => 'default_configuration']);

        $editor->assertSeeHtml('type="range" min="-30" max="30"')
            ->set('config.title_offset', -10)
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => $params['patch'] === [
                'kind' => 'cover-title', 'group' => 'default_configuration', 'position' => null, 'offset' => '-10svh',
            ])
            ->assertDispatched('template-draft-updated', patched: true)
            ->set('config.title_position', 'center')
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => $params['patch']['position'] === 'center');

        // Out of range is refused before anything is stored.
        $editor->set('config.title_offset', 90)->assertHasErrors('config.title_offset');

        // Any other field still reloads the preview.
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateSectionContent::class, ['templateUlid' => $template->ulid, 'type' => 'cover', 'group' => 'default_configuration'])
            ->set('config.date_text', 'Sabtu, 12 Desember 2026')
            ->assertNotDispatched('template-preview-patch');
    }
}
