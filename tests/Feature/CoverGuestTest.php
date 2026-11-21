<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every design greets the guest ("Kepada Yth.") right above the cover's open button.
 */
class CoverGuestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function designs(): array
    {
        return [
            'classic' => ['classic', 'inv-cover-guest', 'inv-cover-button'],
            'botanical-cream' => ['botanical-cream', 'bc-guest', 'data-bc-scroll'],
            'mocha-glass' => ['mocha-glass', 'mg-guest', 'data-mg-open'],
        ];
    }

    #[DataProvider('designs')]
    public function test_the_guest_line_sits_right_above_the_button(string $design, string $guest, string $button): void
    {
        foreach (['design', 'text', 'bottom'] as $position) {
            $html = $this->cover($design, ['button_position' => $position]);

            $this->assertMatchesRegularExpression(
                '/'.preg_quote($guest, '/').'[^>]*>.*?'.preg_quote(__('Dear Sir/Madam'), '/').'.*?'.preg_quote(__('Our honored guest'), '/').'.*?'.preg_quote($button, '/').'/s',
                $html,
                "[{$design}, {$position}]",
            );
        }
    }

    #[DataProvider('designs')]
    public function test_the_greeting_and_the_name_without_a_guest_can_be_changed_or_turned_off(string $design, string $guest): void
    {
        $html = $this->cover($design, ['guest_salutation' => 'Kepada Bapak/Ibu', 'guest_fallback' => 'Keluarga Besar']);
        $this->assertMatchesRegularExpression('/'.preg_quote($guest, '/').'[^>]*>.*?Kepada Bapak\/Ibu.*?Keluarga Besar/s', $html, "[{$design}]");
        $this->assertStringNotContainsString(__('Dear Sir/Madam'), $html, "[{$design}]");

        // Blank texts fall back to the defaults.
        $this->assertStringContainsString(__('Our honored guest'), $this->cover($design, ['guest_salutation' => '  ', 'guest_fallback' => '']), "[{$design}]");

        $html = $this->cover($design, ['guest_greeting' => false]);
        $this->assertStringNotContainsString(__('Dear Sir/Madam'), $html, "[{$design}]");
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\b'.preg_quote($guest, '/').'\b/', $html, "[{$design}]");
    }

    public function test_the_classic_line_takes_the_buttons_side(): void
    {
        $this->assertStringContainsString('inv-cover-guest inv-cover-guest--right', $this->cover('classic', ['button_align' => 'right']));
        $this->assertStringContainsString('inv-cover-guest inv-cover-guest--left', $this->cover('classic', ['alignment' => 'left']));
    }

    /**
     * @param  array<string, string>  $cover
     */
    private function cover(string $design, array $cover): string
    {
        $template = InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => ['cover', 'event']],
            'default_configuration' => ['cover' => ['button_display' => 'label', 'button_label' => 'Buka Undangan', ...$cover]],
        ]);

        return $this->get(route('templates.preview', ['template' => $template, 'content' => 'default_configuration']))->assertOk()->getContent();
    }
}
