<?php

namespace Tests\Unit;

use App\Services\Invitation\ThemeResolver;
use App\Support\Contrast;
use Tests\TestCase;

class ThemeResolverTest extends TestCase
{
    protected bool $seed = false;

    public function test_layers_merge_in_order_default_preset_template_invitation(): void
    {
        $theme = (new ThemeResolver)->resolve(
            ['preset' => 'luxury', 'secondary_color' => '#123456'],
            ['primary_color' => '#abcdef'],
        );

        $this->assertSame('#abcdef', $theme['primary_color']);
        $this->assertSame('#123456', $theme['secondary_color']);
        $this->assertSame('Cinzel', $theme['font_heading']);
        $this->assertSame(config('invitation.default_theme.section_spacing'), $theme['section_spacing']);
    }

    public function test_unsafe_values_fall_back_to_defaults(): void
    {
        $defaults = config('invitation.default_theme');

        $theme = (new ThemeResolver)->resolve([
            'primary_color' => 'red; } body { display:none',
            'font_heading' => 'Evil Font", url(https://evil.test)',
            'button_style' => 'rainbow',
            'unknown_key' => 'ignored',
        ]);

        $this->assertSame($defaults['primary_color'], $theme['primary_color']);
        $this->assertSame($defaults['font_heading'], $theme['font_heading']);
        $this->assertSame($defaults['button_style'], $theme['button_style']);
        $this->assertArrayNotHasKey('unknown_key', $theme);
    }

    public function test_invalid_override_keeps_the_previous_layer_value(): void
    {
        $theme = (new ThemeResolver)->resolve(
            ['primary_color' => '#aa0000'],
            ['primary_color' => 'not-a-color'],
        );

        $this->assertSame('#aa0000', $theme['primary_color']);
    }

    public function test_css_variables_and_font_url_use_whitelisted_fonts(): void
    {
        $resolver = new ThemeResolver;
        $theme = $resolver->resolve(['preset' => 'romantic']);

        $this->assertStringContainsString("--inv-font-heading: 'Great Vibes', cursive;", $resolver->cssVariables($theme));
        $this->assertSame('https://fonts.bunny.net/css?family=great-vibes:400|lora:400,500,600,700&display=swap', $resolver->fontStylesheetUrl($theme));
    }

    public function test_text_on_primary_color_stays_readable(): void
    {
        $resolver = new ThemeResolver;

        $this->assertStringContainsString('--inv-on-primary: #ffffff;', $resolver->cssVariables($resolver->resolve(['primary_color' => '#111111'])));
        // Small accent text stays the secondary color when readable, and darkens toward the text color when not.
        $this->assertStringContainsString('--inv-accent-text: #000000;', $resolver->cssVariables($resolver->resolve(['secondary_color' => '#000000', 'background_color' => '#ffffff'])));
        $accent = $resolver->cssVariables($resolver->resolve(['secondary_color' => '#e8cf8a', 'background_color' => '#f3e7d8', 'text_color' => '#4a2c1a']));
        preg_match('/--inv-accent-text: (#[0-9a-f]{6});/', $accent, $match);
        $this->assertGreaterThanOrEqual(4.5, Contrast::ratio($match[1], '#f3e7d8'));
        $this->assertNotSame('#e8cf8a', $match[1]);

        $this->assertStringContainsString('--inv-on-primary: #1a1a1a;', $resolver->cssVariables($resolver->resolve(['primary_color' => '#f5f5f5'])));
    }
}
