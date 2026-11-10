<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use App\Support\CoverButton;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cover fields "button_position" and "button_align": where the open/scroll button sits (App\Support\CoverButton).
 */
class CoverButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_button_follows_the_text_alignment_by_default(): void
    {
        $this->assertSame('inv-cover-button inv-cover-button--center', CoverButton::classes(null, null, 'center'));
        $this->assertSame('inv-cover-button inv-cover-button--left', CoverButton::classes('design', 'text', 'left'));
        $this->assertSame('inv-cover-button inv-cover-button--right inv-cover-button--bottom', CoverButton::classes('bottom', 'right', 'left'));
        $this->assertSame('inv-cover-button inv-cover-button--center', CoverButton::classes('" onclick="x', 'diagonal', 'center'));
    }

    public function test_the_design_place_differs_per_design(): void
    {
        $this->assertFalse(CoverButton::atBottom('design'));
        $this->assertTrue(CoverButton::atBottom('design', designBottom: true));
        $this->assertFalse(CoverButton::atBottom('text', designBottom: true));
        $this->assertTrue(CoverButton::atBottom('bottom'));
    }

    public function test_classic_pins_the_button_to_the_bottom_bar(): void
    {
        $html = $this->cover('classic', ['button_position' => 'bottom', 'button_align' => 'right']);

        $this->assertMatchesRegularExpression('/<div class="inv-cover-bar">\s*<button type="button" class="inv-cover-button inv-cover-button--right inv-cover-button--bottom/', $html);
        $this->assertSame(1, substr_count($html, 'inv-cover-button '));

        $html = $this->cover('classic', ['alignment' => 'left']);

        $this->assertStringNotContainsString('inv-cover-bar', $html);
        $this->assertStringContainsString('inv-cover-button inv-cover-button--left mt-10', $html);
    }

    public function test_mocha_glass_puts_the_bottom_button_after_the_guest_name(): void
    {
        $html = $this->cover('mocha-glass', ['button_position' => 'bottom', 'button_align' => 'center']);

        $this->assertMatchesRegularExpression('/class="mg-guest".*class="mg-btn-open inv-cover-button inv-cover-button--center inv-cover-button--bottom/s', $html);
        $this->assertSame(1, substr_count($html, 'data-mg-open'));
    }

    public function test_botanical_cream_can_move_the_button_into_the_text(): void
    {
        $this->assertStringContainsString('inv-cover-button inv-cover-button--center inv-cover-button--bottom', $this->cover('botanical-cream', []));

        $html = $this->cover('botanical-cream', ['button_position' => 'text', 'button_align' => 'left']);

        $this->assertMatchesRegularExpression('/class="bc-cover__text[^"]*".*class="bc-scroll inv-animate inv-cover-button inv-cover-button--left"[^>]*>.*<\/a>\s*<\/div>/s', $html);
        $this->assertSame(1, substr_count($html, 'data-bc-scroll'));
    }

    public function test_preview_wishes_say_they_are_not_sent(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'wishes']]]);

        $this->get(route('templates.preview', $template))->assertOk()->assertSee('Preview mode: responses are not saved.');
    }

    /**
     * @param  array<string, mixed>  $cover
     */
    private function cover(string $design, array $cover): string
    {
        $template = InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => ['cover', 'closing']],
            'default_configuration' => ['cover' => ['title' => 'Alya & Pradana', ...$cover]],
        ]);

        return $this->get(route('templates.preview', ['template' => $template, 'content' => 'default_configuration']))->assertOk()->getContent();
    }
}
