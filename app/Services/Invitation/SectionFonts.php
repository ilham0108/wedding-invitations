<?php

namespace App\Services\Invitation;

use App\Services\FontRegistry;
use Illuminate\Validation\Rule;

/**
 * A section's own fonts (template builder, Section panel): heading and body font plus their weights.
 * Each key is optional; a missing key follows the theme. Stored as section "fonts" in the template
 * schema and in invitation_sections.theme. The section element carries them as CSS variables
 * (invitation.css, "Section fonts").
 */
class SectionFonts
{
    public const FONTS = ['font_heading', 'font_body'];

    public const WEIGHTS = ['heading_weight', 'body_weight'];

    public function __construct(private FontRegistry $fonts) {}

    /**
     * Keep the known fonts and weights only; "default", empty and unknown values follow the theme.
     *
     * @return array<string, string>
     */
    public function clean(mixed $fonts): array
    {
        if (! is_array($fonts)) {
            return [];
        }

        $clean = [];

        foreach ([...self::FONTS, ...self::WEIGHTS] as $key) {
            if ($this->isValid($key, $fonts[$key] ?? null)) {
                $clean[$key] = (string) $fonts[$key];
            }
        }

        return $clean;
    }

    public function isValid(string $key, mixed $value): bool
    {
        return match (true) {
            in_array($key, self::FONTS, true) => $this->fonts->has($value),
            in_array($key, self::WEIGHTS, true) => is_scalar($value) && $value !== 'default'
                && in_array((string) $value, config("invitation.theme_options.{$key}"), true),
            default => false,
        };
    }

    /**
     * Validation rules for a section's fonts at $path.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $path): array
    {
        $fonts = Rule::in(array_keys($this->fonts->all()));

        return [
            $path => ['sometimes', 'nullable', 'array'],
            "{$path}.font_heading" => ['nullable', $fonts],
            "{$path}.font_body" => ['nullable', $fonts],
            "{$path}.heading_weight" => ['nullable', Rule::in(config('invitation.theme_options.heading_weight'))],
            "{$path}.body_weight" => ['nullable', Rule::in(config('invitation.theme_options.body_weight'))],
        ];
    }

    /**
     * Inline CSS variables for the section element, or an empty string. Font names come from the
     * registry (letters, digits, spaces and hyphens), so they are safe inside quotes.
     *
     * @param  array<string, string>  $fonts  A cleaned set.
     */
    public function style(array $fonts): string
    {
        $variables = [
            '--inv-font-heading' => isset($fonts['font_heading']) ? $this->fonts->stack($fonts['font_heading']) : null,
            '--inv-font-body' => isset($fonts['font_body']) ? $this->fonts->stack($fonts['font_body']) : null,
            '--inv-heading-weight' => $fonts['heading_weight'] ?? null,
            '--inv-body-weight' => $fonts['body_weight'] ?? null,
        ];

        return collect(array_filter($variables))->map(fn (string $value, string $name): string => "{$name}: {$value};")->implode(' ');
    }

    /**
     * Font names used by these sections, for the page's font stylesheet.
     *
     * @param  array<int, array<string, mixed>>  $sections  Rendered sections (InvitationRenderer).
     * @return array<int, string>
     */
    public function families(array $sections): array
    {
        return collect($sections)
            ->flatMap(fn (array $section): array => array_values(array_intersect_key($section['fonts'] ?? [], array_flip(self::FONTS))))
            ->unique()
            ->values()
            ->all();
    }
}
