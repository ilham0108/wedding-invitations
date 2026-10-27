<?php

namespace App\Services\Invitation;

use Illuminate\Validation\Rule;

/**
 * Merges theme layers (default → preset → template → invitation) and sanitizes
 * every value, because the result is written into an inline <style> block.
 */
class ThemeResolver
{
    private const RADIUS = ['none' => '0', 'small' => '0.375rem', 'medium' => '0.875rem', 'large' => '1.75rem'];

    private const SPACING = ['compact' => '3rem', 'normal' => '5rem', 'relaxed' => '7rem'];

    /**
     * @param  array<string, mixed>  ...$layers
     * @return array<string, string>
     */
    public function resolve(array ...$layers): array
    {
        $theme = config('invitation.default_theme');

        foreach ($layers as $layer) {
            $preset = $layer['preset'] ?? null;

            if (is_string($preset) && isset($this->presets()[$preset])) {
                $theme = array_replace($theme, $this->presets()[$preset]);
            }

            // Invalid values are skipped, so the previous layer's value survives.
            foreach (array_intersect_key($layer, $theme) as $key => $value) {
                if ($this->isValid($key, $value)) {
                    $theme[$key] = $value;
                }
            }
        }

        return $this->sanitize($theme);
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, string>
     */
    public function sanitize(array $theme): array
    {
        $clean = [];

        foreach (config('invitation.default_theme') as $key => $default) {
            $value = $theme[$key] ?? $default;
            $clean[$key] = $this->isValid($key, $value) ? $this->normalize($key, $value) : $default;
        }

        return $clean;
    }

    public function isValid(string $key, mixed $value): bool
    {
        $options = config('invitation.theme_options');

        return match (true) {
            str_ends_with($key, '_color') => is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1,
            str_starts_with($key, 'font_') => is_string($value) && isset(config('invitation.fonts')[$value]),
            isset($options[$key]) => in_array($value, $options[$key], true),
            default => false,
        };
    }

    /**
     * Validation rules for a complete theme form bound at "{$prefix}.*".
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $prefix = 'theme'): array
    {
        $options = config('invitation.theme_options');
        $fonts = array_keys(config('invitation.fonts'));
        $hex = 'regex:/^#[0-9a-fA-F]{6}$/';

        return [
            "{$prefix}.primary_color" => ['required', $hex],
            "{$prefix}.secondary_color" => ['required', $hex],
            "{$prefix}.background_color" => ['required', $hex],
            "{$prefix}.surface_color" => ['required', $hex],
            "{$prefix}.text_color" => ['required', $hex],
            "{$prefix}.font_heading" => ['required', Rule::in($fonts)],
            "{$prefix}.font_body" => ['required', Rule::in($fonts)],
            "{$prefix}.button_style" => ['required', Rule::in($options['button_style'])],
            "{$prefix}.border_radius" => ['required', Rule::in($options['border_radius'])],
            "{$prefix}.section_spacing" => ['required', Rule::in($options['section_spacing'])],
        ];
    }

    private function normalize(string $key, string $value): string
    {
        return str_ends_with($key, '_color') ? strtolower($value) : $value;
    }

    /**
     * @param  array<string, string>  $theme  A sanitized theme.
     */
    public function cssVariables(array $theme): string
    {
        $fonts = config('invitation.fonts');

        $variables = [
            '--inv-primary' => $theme['primary_color'],
            '--inv-on-primary' => $this->readableTextOn($theme['primary_color']),
            '--inv-secondary' => $theme['secondary_color'],
            '--inv-bg' => $theme['background_color'],
            '--inv-surface' => $theme['surface_color'],
            '--inv-text' => $theme['text_color'],
            '--inv-font-heading' => "'{$theme['font_heading']}', {$fonts[$theme['font_heading']]['fallback']}",
            '--inv-font-body' => "'{$theme['font_body']}', {$fonts[$theme['font_body']]['fallback']}",
            '--inv-radius' => self::RADIUS[$theme['border_radius']],
            '--inv-spacing' => self::SPACING[$theme['section_spacing']],
        ];

        return collect($variables)->map(fn (string $value, string $name): string => "{$name}: {$value};")->implode(' ');
    }

    /**
     * @param  array<string, string>  $theme  A sanitized theme.
     * @param  array<int, string>  $extraFonts  Fonts a design always needs (unknown names are ignored).
     */
    public function fontStylesheetUrl(array $theme, array $extraFonts = []): string
    {
        $fonts = config('invitation.fonts');
        $families = collect([$theme['font_heading'], $theme['font_body'], ...$extraFonts])
            ->filter(fn (string $font): bool => isset($fonts[$font]))
            ->unique()
            ->map(fn (string $font): string => $fonts[$font]['bunny'])
            ->implode('|');

        return 'https://fonts.bunny.net/css?family='.$families.'&display=swap';
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function presets(): array
    {
        return config('invitation.theme_presets');
    }

    private function readableTextOn(string $hexColor): string
    {
        [$red, $green, $blue] = sscanf($hexColor, '#%02x%02x%02x');
        $luminance = (0.299 * $red + 0.587 * $green + 0.114 * $blue) / 255;

        return $luminance > 0.6 ? '#1a1a1a' : '#ffffff';
    }
}
