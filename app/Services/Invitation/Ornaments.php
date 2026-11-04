<?php

namespace App\Services\Invitation;

use App\Support\SafeUrl;

/**
 * Cleans a section's ornament list (config "ornament_options"): a safe image URL, a position
 * in percent of the section, an anchor point, a layer, and an entrance and motion with their timing.
 * Everything that reaches the ornaments partial has been through clean().
 */
class Ornaments
{
    /** Where the anchor sits inside the image (percent of its box): x, y. */
    private const ANCHOR_POINTS = [
        'top-left' => [0, 0], 'top' => [50, 0], 'top-right' => [100, 0],
        'left' => [0, 50], 'center' => [50, 50], 'right' => [100, 50],
        'bottom-left' => [0, 100], 'bottom' => [50, 100], 'bottom-right' => [100, 100],
    ];

    /** Where each entrance starts (it ends at "none"): x and y offset in px, and scale. */
    private const ENTER_FROM = [
        'fade' => [0, 0, 1],
        'fade-up' => [0, 40, 1],
        'fade-down' => [0, -40, 1],
        'fade-left' => [40, 0, 1],
        'fade-right' => [-40, 0, 1],
        'zoom-in' => [0, 0, 0.6],
        'zoom-in-up' => [0, 40, 0.6],
        'zoom-in-left' => [40, 0, 0.6],
        'zoom-in-right' => [-40, 0, 0.6],
        'zoom-out' => [0, 0, 1.3],
    ];

    /**
     * Inline style of a cleaned ornament's outer box. Shared by the ornaments partial and the
     * template builder's live preview, so both place an ornament identically.
     *
     * @param  array<string, mixed>  $ornament  A cleaned ornament.
     * @param  int  $phase  Position among the ornaments of its layer (staggers sway and float).
     */
    public function style(array $ornament, int $phase): string
    {
        [$anchorX, $anchorY] = self::ANCHOR_POINTS[$ornament['anchor']];
        $flipX = $ornament['flip'];
        $flipY = $ornament['flip_y'] ?? false;
        // A mirrored box mirrors what is inside it: flip the pivot, and the entrance back, on each mirrored axis.
        $originX = $flipX ? 100 - $anchorX : $anchorX;
        $originY = $flipY ? 100 - $anchorY : $anchorY;

        return "left: {$ornament['x']}%; top: {$ornament['y']}%; width: {$ornament['width']}%; translate: -{$anchorX}% -{$anchorY}%; "
            ."--inv-ornament-origin: {$originX}% {$originY}%; --inv-ornament-from: ".$this->enterFrom($ornament['enter'], $flipX, $flipY).'; '
            ."--inv-ornament-duration: {$ornament['duration']}s; --inv-ornament-delay: {$ornament['delay']}s; --inv-ornament-phase: ".($phase * -1.3).'s;';
    }

    /**
     * @param  array<string, mixed>  $ornament  A cleaned ornament.
     */
    public function classes(array $ornament): string
    {
        return collect([
            'inv-ornament',
            $ornament['enter'] !== 'none' ? 'inv-ornament--enter' : null,
            'inv-ornament--'.$ornament['motion'],
            $ornament['flip'] ? 'inv-ornament--flip' : null,
            ($ornament['flip_y'] ?? false) ? 'inv-ornament--flip-y' : null,
        ])->filter()->implode(' ');
    }

    /**
     * Position of an ornament among the ornaments of its own layer.
     *
     * @param  array<int, array<string, mixed>>  $ornaments  A cleaned list.
     */
    public function phase(array $ornaments, int $index): int
    {
        return collect($ornaments)->take($index)->where('layer', $ornaments[$index]['layer'])->count();
    }

    /**
     * @return array<int, array{image: string, x: float, y: float, width: float, anchor: string, flip: bool, flip_y: bool, layer: string, enter: string, duration: float, delay: float, motion: string}>
     */
    public function clean(mixed $ornaments): array
    {
        $options = config('invitation.ornament_options');
        $defaults = $options['defaults'];

        return collect(is_array($ornaments) ? $ornaments : [])
            ->filter(fn (mixed $ornament): bool => is_array($ornament) && SafeUrl::filter($ornament['image'] ?? null) !== null)
            ->map(fn (array $ornament): array => [
                'image' => SafeUrl::filter($ornament['image']),
                'x' => $this->number($ornament['x'] ?? null, $defaults['x'], -50, 150),
                'y' => $this->number($ornament['y'] ?? null, $defaults['y'], -50, 150),
                'width' => $this->number($ornament['width'] ?? null, $defaults['width'], 2, 150),
                'anchor' => $this->choice($ornament['anchor'] ?? null, $options['anchors'], $defaults['anchor']),
                'flip' => (bool) ($ornament['flip'] ?? false),
                'flip_y' => (bool) ($ornament['flip_y'] ?? false),
                'layer' => $this->choice($ornament['layer'] ?? null, $options['layers'], $defaults['layer']),
                'enter' => $this->choice($ornament['enter'] ?? null, $options['enters'], $defaults['enter']),
                'duration' => $this->number($ornament['duration'] ?? null, $defaults['duration'], 0.2, 8),
                'delay' => $this->number($ornament['delay'] ?? null, $defaults['delay'], 0, 8),
                'motion' => $this->choice($ornament['motion'] ?? null, $options['motions'], $defaults['motion']),
            ])
            ->take($options['max_per_section'])
            ->values()
            ->all();
    }

    /**
     * Convert the first ornament format (one template-wide set placed by edge "position",
     * "size" and "motion": none|sway|float|rise) to the per-section format.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fromLegacy(mixed $ornaments): array
    {
        $positions = [
            'top-left' => [0, 0, 'top-left', 'fade-down'],
            'top-right' => [100, 0, 'top-right', 'fade-down'],
            'bottom-left' => [0, 100, 'bottom-left', 'fade-up'],
            'bottom-right' => [100, 100, 'bottom-right', 'fade-up'],
            'left' => [0, 50, 'left', 'fade-right'],
            'right' => [100, 50, 'right', 'fade-left'],
            'bottom' => [50, 100, 'bottom', 'fade-up'],
        ];
        $widths = ['small' => 24, 'medium' => 36, 'large' => 52];

        return collect(is_array($ornaments) ? $ornaments : [])
            ->filter(fn (mixed $ornament): bool => is_array($ornament))
            ->map(function (array $ornament) use ($positions, $widths): array {
                [$x, $y, $anchor, $rise] = $positions[$ornament['position'] ?? ''] ?? $positions['bottom-left'];
                $motion = $ornament['motion'] ?? 'sway';

                return [
                    'image' => $ornament['image'] ?? null,
                    'x' => $x,
                    'y' => $y,
                    'width' => $widths[$ornament['size'] ?? ''] ?? 36,
                    'anchor' => $anchor,
                    'flip' => (bool) ($ornament['flip'] ?? false),
                    'layer' => 'front',
                    'enter' => $motion === 'rise' ? $rise : 'none',
                    'duration' => 1.3,
                    'delay' => 0,
                    'motion' => $motion === 'rise' ? 'sway' : ($motion === 'float' ? 'float' : ($motion === 'none' ? 'none' : 'sway')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Starting transform of an entrance, inside a box mirrored on $flipX / $flipY: the offset is mirrored
     * back, so "fade-up" still rises on screen.
     */
    private function enterFrom(string $enter, bool $flipX, bool $flipY): string
    {
        [$x, $y, $scale] = self::ENTER_FROM[$enter] ?? [0, 0, 1];
        $x = $flipX ? -$x : $x;
        $y = $flipY ? -$y : $y;

        $transform = trim(($x !== 0 ? "translateX({$x}px) " : '').($y !== 0 ? "translateY({$y}px) " : '').($scale !== 1 ? "scale({$scale})" : ''));

        return $transform === '' ? 'none' : $transform;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function choice(mixed $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function number(mixed $value, int|float $default, int|float $min, int|float $max): float
    {
        return is_numeric($value) ? round(max($min, min($max, (float) $value)), 2) : (float) $default;
    }
}
