<?php

namespace App\Services\Invitation;

use App\Support\Contrast;

/**
 * A section's own colors (template builder, Section panel): "heading" for its titles and names, "text" for its
 * running text, "social_icon" and "social_bg" for its social links. Each key is optional; a missing key follows
 * the theme or the design. Stored as section "colors" in the template schema and in invitation_sections.theme;
 * invitation.css ("Section colors") applies them.
 */
class SectionColors
{
    public const KEYS = ['heading', 'text', 'social_icon', 'social_bg'];

    private const HEX = '/^#[0-9a-fA-F]{6}$/';

    /**
     * Keep the known keys with a valid "#rrggbb" value, lowercased.
     *
     * @return array<string, string>
     */
    public function clean(mixed $colors): array
    {
        if (! is_array($colors)) {
            return [];
        }

        $clean = [];

        foreach (self::KEYS as $key) {
            if ($this->isValid($key, $colors[$key] ?? null)) {
                $clean[$key] = strtolower($colors[$key]);
            }
        }

        return $clean;
    }

    public function isValid(string $key, mixed $value): bool
    {
        return in_array($key, self::KEYS, true) && is_string($value) && preg_match(self::HEX, $value) === 1;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $path): array
    {
        return [
            $path => ['sometimes', 'nullable', 'array'],
            "{$path}.heading" => ['nullable', 'regex:'.self::HEX],
            "{$path}.text" => ['nullable', 'regex:'.self::HEX],
            "{$path}.social_icon" => ['nullable', 'regex:'.self::HEX],
            "{$path}.social_bg" => ['nullable', 'regex:'.self::HEX],
        ];
    }

    /**
     * Inline CSS variables for the section element, or an empty string.
     *
     * @param  array<string, string>  $colors  A cleaned set.
     */
    public function style(array $colors): string
    {
        return collect([
            '--inv-heading-color' => $colors['heading'] ?? null,
            '--inv-section-text' => $colors['text'] ?? null,
            '--inv-social-icon' => $colors['social_icon'] ?? null,
            '--inv-social-bg' => $colors['social_bg'] ?? null,
        ])->filter()->map(fn (string $value, string $name): string => "{$name}: {$value};")->implode(' ');
    }

    /**
     * The theme color behind a section with this "background" option (without a background photo).
     *
     * @param  array<string, string>  $theme  A resolved theme.
     */
    public function background(array $theme, string $background): string
    {
        return match ($background) {
            'muted' => $theme['surface_color'],
            'primary' => $theme['primary_color'],
            default => $theme['background_color'],
        };
    }

    /**
     * Contrast of each chosen color against the section background, with the WCAG minimum it must reach
     * (headings are large text). A social icon is measured against its own background, once both are chosen:
     * without one, the design decides what is behind the icon.
     *
     * @param  array<string, string>  $colors  A cleaned set.
     * @return array<string, array{ratio: float, minimum: float}>
     */
    public function contrast(array $colors, string $background): array
    {
        $contrast = collect($colors)->only(['heading', 'text'])->map(fn (string $color, string $key): array => [
            'ratio' => Contrast::ratio($color, $background),
            'minimum' => $key === 'heading' ? Contrast::LARGE_TEXT : Contrast::TEXT,
        ])->all();

        if (isset($colors['social_icon'], $colors['social_bg'])) {
            $contrast['social_icon'] = ['ratio' => Contrast::ratio($colors['social_icon'], $colors['social_bg']), 'minimum' => Contrast::LARGE_TEXT];
        }

        return $contrast;
    }
}
