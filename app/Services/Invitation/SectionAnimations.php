<?php

namespace App\Services\Invitation;

use Illuminate\Validation\Rule;

/**
 * Entrance animation of single elements of a section (template builder, Section panel, "Animation"): the
 * title, a text, a photo, every item of a list. The elements are listed per section type in
 * config("invitation.animation_targets"). Each one has an effect and a delay in tenths of a second.
 *
 * Stored as section "animations" in the template schema and in invitation_sections.theme, as
 * {element: {effect, delay, pattern?}}, holding only the elements that differ from the default. Once a section has
 * any, its elements animate one by one (the @animate directive in the section views) and the design's
 * block animation (.inv-animate) stays still inside it; elements left on "default" use the theme's effect.
 * A list (config "invitation.animation_lists") also has a pattern: every item the same, or "zigzag", where the
 * items come in from the left and the right in turn. A pattern fits any number of items, which the client sets.
 */
class SectionAnimations
{
    public const EFFECTS = ['default', 'none', 'fade', 'fade-up', 'fade-down', 'fade-left', 'fade-right', 'zoom-in', 'zoom-out'];

    public const PATTERNS = ['same', 'zigzag'];

    /** Highest delay, in tenths of a second. */
    public const MAX_DELAY = 10;

    /** Every further item of a list waits this many tenths more, up to MAX_STAGGER, so the items arrive in turn. */
    private const MAX_STAGGER = 5;

    /** The theme's effects (decoration "animation") in the terms of EFFECTS. */
    private const THEME_EFFECTS = ['fade' => 'fade', 'fade-up' => 'fade-up', 'zoom' => 'zoom-in', 'slide' => 'fade-right'];

    /**
     * The elements a section type can animate, element key => label.
     *
     * @return array<string, string>
     */
    public function targets(string $type): array
    {
        return config("invitation.animation_targets.{$type}", []);
    }

    /**
     * Whether the element is a list whose items animate one by one (and can take a pattern).
     */
    public function isList(string $type, string $key): bool
    {
        return in_array($key, config("invitation.animation_lists.{$type}", []), true);
    }

    /**
     * Keep the known elements of the type with a valid effect and delay, dropping the ones left on the default.
     *
     * @return array<string, array{effect: string, delay: int}>
     */
    public function clean(string $type, mixed $animations): array
    {
        if (! is_array($animations)) {
            return [];
        }

        $clean = [];

        foreach (array_keys($this->targets($type)) as $key) {
            $animation = $animations[$key] ?? null;

            if (! is_array($animation)) {
                continue;
            }

            $effect = in_array($animation['effect'] ?? null, self::EFFECTS, true) ? $animation['effect'] : 'default';
            $delay = is_numeric($animation['delay'] ?? null) ? max(0, min(self::MAX_DELAY, (int) $animation['delay'])) : 0;

            $pattern = $this->isList($type, $key) && ($animation['pattern'] ?? null) === 'zigzag' ? 'zigzag' : 'same';

            if ($effect !== 'default' || $delay > 0 || $pattern !== 'same') {
                $clean[$key] = ['effect' => $effect, 'delay' => $delay, ...($pattern === 'same' ? [] : ['pattern' => $pattern])];
            }
        }

        return $clean;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $path): array
    {
        return [
            $path => ['sometimes', 'nullable', 'array'],
            "{$path}.*" => ['array'],
            "{$path}.*.effect" => ['nullable', Rule::in(self::EFFECTS)],
            "{$path}.*.delay" => ['nullable', 'integer', 'between:0,'.self::MAX_DELAY],
            "{$path}.*.pattern" => ['nullable', Rule::in(self::PATTERNS)],
        ];
    }

    /**
     * HTML attributes of one element ("@animate('key')", or "@animate('key', $loop->index)" for a list item).
     * Empty while the section has no animations of its own, or when the element ends up without one.
     *
     * @param  array<string, mixed>|null  $section  A rendered section (InvitationRenderer::buildSection).
     */
    public function attributes(?array $section, ?string $themeAnimation, string $key, int $index = 0): string
    {
        $animations = $section['animations'] ?? [];

        if ($animations === [] || ! array_key_exists($key, $this->targets((string) ($section['type'] ?? '')))) {
            return '';
        }

        $animation = $animations[$key] ?? ['effect' => 'default', 'delay' => 0];
        $effect = $animation['effect'] === 'default' ? (self::THEME_EFFECTS[$themeAnimation] ?? 'none') : $animation['effect'];

        // The theme's "slide" alternates sides; list items do the same.
        if ($animation['effect'] === 'default' && $themeAnimation === 'slide' && $index % 2 === 1) {
            $effect = 'fade-left';
        }

        if ($effect === 'none') {
            return '';
        }

        if (($animation['pattern'] ?? 'same') === 'zigzag') {
            $effect = $this->zigzag($effect, $index);
        }

        $delay = $animation['delay'] + min($index, self::MAX_STAGGER);

        return ' data-inv-anim="'.$effect.'"'.($delay > 0 ? ' data-inv-delay="'.$delay.'"' : '');
    }

    /**
     * The effect of list item $index in a zigzag: the first item comes in from the side the effect names (from the
     * left when it names none), the next from the other side. Zooms keep zooming while they come in from a side.
     */
    private function zigzag(string $effect, int $index): string
    {
        $fromRight = ($effect === 'fade-left') !== ($index % 2 === 1);

        return str_starts_with($effect, 'zoom')
            ? ($fromRight ? 'zoom-in-left' : 'zoom-in-right')
            : ($fromRight ? 'fade-left' : 'fade-right');
    }
}
