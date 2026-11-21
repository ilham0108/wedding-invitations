<?php

namespace App\Services\Invitation;

/**
 * The floating section menu of a page (invitation.partials.nav). It is shown when the decoration says so
 * ("nav_display"), or when the design shows one by default. Its entries are the admin's own ("nav_items"),
 * else the design's section types in page order. Each entry links to the first section of its type on the
 * page, so an entry whose section is hidden or missing is left out, and a menu of fewer than two entries is not shown.
 */
class NavigationMenu
{
    public function __construct(private DesignRegistry $designs, private DecorationResolver $decorations) {}

    /**
     * @param  array<int, array{key: string, type: string}>  $sections  Rendered sections, in page order.
     * @param  array<string, mixed>  $decoration  Resolved decoration.
     * @return array<int, array{key: string, type: string, label: string, icon: string}>
     */
    public function items(array $sections, array $decoration, string $design): array
    {
        if (! $this->isShown($decoration, $design)) {
            return [];
        }

        $first = collect($sections)->unique('type')->keyBy('type');
        $entries = $this->entries($decoration, $design, $first->keys()->all());

        $items = collect($entries)
            ->filter(fn (array $entry): bool => $first->has($entry['type']))
            ->map(fn (array $entry): array => [
                'key' => $first[$entry['type']]['key'],
                'type' => $entry['type'],
                'label' => $entry['label'] !== '' ? $entry['label'] : $this->defaultLabel($entry['type']),
                'icon' => config('invitation.navigation.icons')[$entry['icon']]['class'],
            ])
            ->values()
            ->all();

        return count($items) >= 2 ? $items : [];
    }

    /**
     * @param  array<string, mixed>  $decoration
     */
    public function isShown(array $decoration, string $design): bool
    {
        return match ($decoration['nav_display'] ?? 'design') {
            'show' => true,
            'hide' => false,
            default => $this->designs->nav($design)['show'],
        };
    }

    /**
     * The entries as stored, or the ones picked automatically from the given section types (page order).
     *
     * @param  array<string, mixed>  $decoration
     * @param  array<int, string>  $types
     * @return array<int, array{type: string, label: string, icon: string}>
     */
    public function entries(array $decoration, string $design, array $types): array
    {
        $stored = $this->decorations->navItems($decoration['nav_items'] ?? []);

        if ($stored !== []) {
            return $stored;
        }

        $auto = $this->designs->nav($design)['auto'];

        return $this->decorations->navItems(collect($types)
            ->filter(fn (string $type): bool => in_array($type, $auto, true))
            ->map(fn (string $type): array => ['type' => $type, 'label' => '', 'icon' => $this->defaultIcon($type)])
            ->all());
    }

    public function defaultLabel(string $type): string
    {
        return __(config("invitation.navigation.defaults.{$type}.label") ?? app(SectionRegistry::class)->label($type));
    }

    public function defaultIcon(string $type): string
    {
        return config("invitation.navigation.defaults.{$type}.icon") ?? array_key_first(config('invitation.navigation.icons'));
    }
}
