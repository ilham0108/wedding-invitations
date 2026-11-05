<?php

namespace Tests\Feature;

use App\Enums\TemplateStatus;
use App\Models\InvitationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateCardTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'botanical-cream', 'mocha-glass'];

    private const ORNAMENT = ['image' => '/illustrations/ornament-flower-corner.svg', 'x' => 0, 'y' => 100, 'width' => 40, 'anchor' => 'bottom-left', 'flip' => false, 'layer' => 'front', 'enter' => 'fade-up', 'duration' => 1, 'delay' => 0, 'motion' => 'sway'];

    public function test_the_card_shows_the_template_cover_in_a_frame(): void
    {
        $template = InvitationTemplate::factory()->create([
            'sample_content' => ['cover' => ['title' => 'Arina & Bagas', 'background_image' => '/storage/templates/cover.webp']],
        ]);

        $this->blade('<x-template-card :template="$template" />', ['template' => $template])
            ->assertSee('/storage/templates/cover.webp', false)
            ->assertSee(route('templates.card', $template), false)
            ->assertSee('v='.$template->updated_at->timestamp, false)
            ->assertSee('data-src=', false)
            ->assertSee('x-intersect.once', false)
            ->assertSee('tabindex="-1" aria-hidden="true"', false);
    }

    public function test_an_uploaded_thumbnail_wins_over_the_cover(): void
    {
        $template = InvitationTemplate::factory()->create([
            'thumbnail' => 'templates/thumbnails/custom.webp',
            'sample_content' => ['cover' => ['background_image' => '/storage/templates/cover.webp']],
        ]);

        $this->blade('<x-template-card :template="$template" />', ['template' => $template])
            ->assertSee('templates/thumbnails/custom.webp', false)
            ->assertDontSee('/storage/templates/cover.webp', false)
            ->assertDontSee('<iframe', false);
    }

    public function test_an_unsafe_photo_is_left_out_of_the_card(): void
    {
        $template = InvitationTemplate::factory()->create([
            'sample_content' => ['cover' => ['background_image' => 'javascript:alert(1)']],
        ]);

        $this->blade('<x-template-card :template="$template" />', ['template' => $template])
            ->assertDontSee('javascript:alert', false)
            ->assertSee('<iframe', false);
    }

    public function test_the_frame_shows_the_real_cover_as_a_still_page_in_every_design(): void
    {
        foreach (self::DESIGNS as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => [['type' => 'cover', 'ornaments' => [self::ORNAMENT]], 'event', 'closing']],
                'sample_content' => ['cover' => ['title' => 'Arina & Bagas', 'title_position' => 'top'], 'closing' => ['heading' => 'Sampai jumpa']],
            ]);

            $response = $this->get(route('templates.card', $template))->assertOk();
            $html = $response->getContent();

            $this->assertStringContainsString('ornament-flower-corner.svg', $html, "[{$design}]");
            $this->assertStringContainsString('data-inv-title-position="top"', $html, "[{$design}]");
            $this->assertStringContainsString('inv-static', $html, "[{$design}]");
            // Only the cover, and nothing that runs or plays.
            $this->assertStringNotContainsString('Sampai jumpa', $html, "[{$design}]");
            $this->assertStringNotContainsString('<script', $html, "[{$design}]");
            $this->assertStringNotContainsString('inv-preview-bar', $html, "[{$design}]");
            $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'), "[{$design}]");
        }
    }

    public function test_a_template_without_a_cover_shows_its_first_section(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['closing', 'event']]]);

        $this->get(route('templates.card', $template))
            ->assertOk()
            ->assertSee('data-section="closing"', false)
            ->assertDontSee('data-section="event"', false);
    }

    public function test_draft_frames_are_only_for_template_admins(): void
    {
        $template = InvitationTemplate::factory()->create(['status' => TemplateStatus::Draft]);

        $this->get(route('templates.card', $template))->assertNotFound();
        $this->actingAs(User::factory()->client()->create())->get(route('templates.card', $template))->assertNotFound();
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('templates.card', $template))->assertOk();
    }
}
