<?php

namespace App\Services\Invitation;

use App\Models\Illustration;
use Illuminate\Support\Str;

/**
 * Merges decoration layers (default → template → invitation) and keeps only
 * values listed in config('invitation.decoration_options').
 */
class DecorationResolver
{
    /**
     * Every value is a string, except "nav_items" (see navItems()).
     *
     * @param  array<string, mixed>  ...$layers
     * @return array<string, mixed>
     */
    public function resolve(array ...$layers): array
    {
        $decoration = config('invitation.default_decoration');

        foreach ($layers as $layer) {
            foreach (array_intersect_key($layer, $decoration) as $key => $value) {
                if ($key === 'nav_items') {
                    $decoration[$key] = $this->navItems($value);
                } elseif ($this->isValid($key, $value)) {
                    $decoration[$key] = $value;
                }
            }
        }

        return $decoration;
    }

    /**
     * The entries of the floating menu, as stored: at most "max_items", one per known section type, with an icon
     * from config('invitation.navigation.icons') and a label of at most "label_max" characters ("" = the type's own).
     * Anything else is dropped.
     *
     * @return array<int, array{type: string, label: string, icon: string}>
     */
    public function navItems(mixed $items): array
    {
        $options = config('invitation.navigation');
        $sections = app(SectionRegistry::class);

        return collect(is_array($items) ? array_values($items) : [])
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['type'] ?? null) && $sections->has($item['type']))
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'label' => is_string($item['label'] ?? null) ? Str::limit(Str::squish($item['label']), $options['label_max'], '') : '',
                'icon' => is_string($item['icon'] ?? null) && isset($options['icons'][$item['icon']])
                    ? $item['icon']
                    : ($options['defaults'][$item['type']]['icon'] ?? array_key_first($options['icons'])),
            ])
            ->unique('type')
            ->take($options['max_items'])
            ->values()
            ->all();
    }

    public function isValid(string $key, mixed $value): bool
    {
        // Ornament images (under headings, as divider) are illustration ULIDs (never a URL), or empty.
        if (in_array($key, ['heading_ornament_image', 'divider_image'], true)) {
            return $value === '' || (is_string($value) && Str::isUlid($value));
        }

        return in_array($value, $this->options()[$key] ?? [], true);
    }

    /**
     * The decoration of a rendered page: resolve() plus "heading_ornament_url" and "divider_url". A library
     * image that is gone turns the heading ornament / divider back into the design's own.
     *
     * @param  array<string, mixed>  ...$layers
     * @return array<string, string>
     */
    public function forPage(array ...$layers): array
    {
        $decoration = $this->resolve(...$layers);
        $url = $this->headingOrnamentUrl($decoration);
        $dividerUrl = $this->dividerUrl($decoration);

        if ($url === null && $decoration['heading_ornament'] === 'image') {
            $decoration['heading_ornament'] = 'design';
        }

        if ($dividerUrl === null && $decoration['divider'] === 'image') {
            $decoration['divider'] = 'line';
        }

        return [...$decoration, 'heading_ornament_url' => (string) $url, 'divider_url' => (string) $dividerUrl];
    }

    /**
     * URL of the library ornament used as divider, or null when the divider is not an image
     * (or its image is missing or hidden).
     *
     * @param  array<string, string>  $decoration  resolved decoration
     */
    public function dividerUrl(array $decoration): ?string
    {
        return $decoration['divider'] === 'image' && $decoration['divider_image'] !== ''
            ? Illustration::query()->active()->where('ulid', Str::lower($decoration['divider_image']))->first()?->url()
            : null;
    }

    /**
     * URL of the ornament under section headings, or null for the design's own / none.
     * A missing or hidden library image falls back to the design's own ornament.
     *
     * @param  array<string, string>  $decoration  resolved decoration
     */
    public function headingOrnamentUrl(array $decoration): ?string
    {
        return match ($decoration['heading_ornament']) {
            'design', 'none' => null,
            'image' => Illustration::query()->active()->where('ulid', Str::lower($decoration['heading_ornament_image']))->first()?->url(),
            default => '/images/heading-ornaments/'.$decoration['heading_ornament'].'.svg',
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function options(): array
    {
        return config('invitation.decoration_options');
    }
}
