<?php

namespace Tests\Feature;

use App\Enums\IllustrationCategory;
use App\Livewire\TemplateBuilder;
use App\Livewire\TemplateSectionContent;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mocha Glass shows a photo panel beside the invitation on wide screens; the cover can give it
 * its own photo, texts and ornaments ("desktop_panel" design feature).
 */
class DesktopPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $cover
     * @param  array<int, array<string, mixed>>  $desktopOrnaments
     */
    private function template(string $design, array $cover, array $desktopOrnaments = []): InvitationTemplate
    {
        return InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => [['type' => 'cover', 'desktop_ornaments' => $desktopOrnaments], 'closing']],
            'sample_content' => ['cover' => ['title' => 'Arina & Bagas', 'subtitle' => 'The Wedding of', 'background_image' => '/images/demo/cover.svg', ...$cover]],
        ]);
    }

    public function test_the_desktop_panel_has_its_own_photo_texts_and_ornaments(): void
    {
        $template = $this->template('mocha-glass', [
            'desktop_image' => '/images/demo/gallery-1.svg',
            'desktop_title' => 'Selena & Milo',
            'desktop_text' => 'Kepada Yth, Bapak/Ibu/Saudara/i',
            'desktop_text_position' => 'bottom',
        ], [[
            'image' => '/illustrations/ornament-hanging-branch.svg', 'x' => 0, 'y' => 0, 'width' => 30, 'anchor' => 'top-left',
            'flip' => false, 'layer' => 'front', 'enter' => 'zoom-in', 'duration' => 2, 'delay' => 1, 'motion' => 'sway',
        ]]);

        $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();
        preg_match('#<aside class="mg-hero.*?</aside>#s', $html, $panel);

        $this->assertStringContainsString('mg-hero--text-bottom', $panel[0]);
        $this->assertStringContainsString('gallery-1.svg', $panel[0]);
        $this->assertStringContainsString('Selena &amp; Milo', $panel[0]);
        $this->assertStringContainsString('Kepada Yth, Bapak/Ibu/Saudara/i', $panel[0]);
        $this->assertStringContainsString('ornament-hanging-branch.svg', $panel[0]);
        // The phone cover keeps its own title and photo.
        $this->assertStringContainsString('Arina', $html);
        $this->assertSame(1, substr_count($html, 'ornament-hanging-branch.svg'));
    }

    public function test_an_empty_desktop_panel_falls_back_to_the_cover(): void
    {
        $html = $this->get(route('templates.preview', $this->template('mocha-glass', [])))->assertOk()->getContent();
        preg_match('#<aside class="mg-hero.*?</aside>#s', $html, $panel);

        $this->assertStringContainsString('mg-hero--text-center', $panel[0]);
        $this->assertStringContainsString('cover.svg', $panel[0]);
        $this->assertStringContainsString('Arina &amp; Bagas', $panel[0]);
        $this->assertStringContainsString('The Wedding of', $panel[0]);
    }

    public function test_desktop_fields_only_show_for_designs_with_a_desktop_panel(): void
    {
        $admin = User::factory()->superAdmin()->create();

        foreach (['mocha-glass' => true, 'classic' => false] as $design => $shown) {
            $template = $this->template($design, []);
            $component = Livewire::actingAs($admin)->test(TemplateSectionContent::class, [
                'templateUlid' => $template->ulid, 'type' => 'cover', 'group' => 'default_configuration',
            ]);

            $shown ? $component->assertSee('Desktop panel: title') : $component->assertDontSee('Desktop panel: title');
        }
    }

    public function test_admins_place_ornaments_on_the_desktop_panel(): void
    {
        $ornament = Illustration::query()->create([
            'category' => IllustrationCategory::Ornament, 'name' => 'Ranting', 'disk' => null, 'path' => 'illustrations/ornament-hanging-branch.svg',
            'width' => 400, 'height' => 400, 'is_premium' => false, 'is_active' => true, 'sort_order' => 1,
        ]);
        $admin = User::factory()->superAdmin()->create();

        foreach (['mocha-glass' => 'desktop_ornaments', 'classic' => 'ornaments'] as $design => $list) {
            $template = $this->template($design, []);

            Livewire::actingAs($admin)
                ->test(TemplateBuilder::class, ['template' => $template])
                ->set('ornamentTarget', 'desktop_ornaments')
                ->call('addOrnament', $ornament->ulid)
                ->call('save');

            // Classic has no desktop panel, so the ornament lands on the cover itself.
            $cover = $template->fresh()->schema['sections'][0];
            $this->assertCount(1, $cover[$list], "[{$design}]");
            $this->assertArrayNotHasKey($list === 'ornaments' ? 'desktop_ornaments' : 'ornaments', $cover, "[{$design}]");
        }
    }

    public function test_invitations_keep_the_desktop_panel_ornaments(): void
    {
        $template = $this->template('mocha-glass', [], [[
            'image' => '/illustrations/ornament-side-vine.svg', 'x' => 0, 'y' => 50, 'width' => 10, 'anchor' => 'left',
            'flip' => false, 'layer' => 'back', 'enter' => 'fade', 'duration' => 1, 'delay' => 0, 'motion' => 'none',
        ]]);

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Arina & Bagas', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->assertCount(1, $invitation->sections()->first()->theme['desktop_ornaments']);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertSee('ornament-side-vine.svg', false);
    }
}
