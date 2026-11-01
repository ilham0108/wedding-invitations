<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Looks up font families in the Bunny Fonts catalog (https://fonts.bunny.net/list).
 * The catalog is cached for a day, reduced to the fields used here.
 */
class BunnyFontCatalog
{
    private const LIST_URL = 'https://fonts.bunny.net/list';

    /** Weights loaded when the family has them (the built-in fonts use the same range). */
    private const WEIGHTS = [300, 400, 500, 600, 700];

    /**
     * The family matching $name (by its slug, e.g. "Cormorant Infant" → "cormorant-infant").
     *
     * @return array{name: string, family: string, category: string, weights: array<int, int>}|null
     *
     * @throws \Illuminate\Http\Client\RequestException When the catalog cannot be fetched.
     */
    public function find(string $name): ?array
    {
        $slug = Str::slug($name);
        $font = $this->catalog()[$slug] ?? null;

        if ($slug === '' || $font === null) {
            return null;
        }

        $weights = array_values(array_intersect(self::WEIGHTS, $font['weights'])) ?: array_slice($font['weights'], 0, 3);

        return [
            'name' => $font['name'],
            'family' => $slug.':'.implode(',', $weights),
            'category' => match ($font['category']) {
                'serif', 'sans-serif', 'display' => $font['category'],
                'handwriting' => 'handwritten',
                default => 'sans-serif',
            },
            'weights' => $weights,
        ];
    }

    /**
     * @return array<string, array{name: string, category: string, weights: array<int, int>}>
     */
    private function catalog(): array
    {
        return Cache::remember('fonts.bunny-catalog.v1', now()->addDay(), fn (): array => collect(
            Http::timeout(10)->acceptJson()->get(self::LIST_URL)->throw()->json() ?? []
        )
            ->filter(fn (mixed $font): bool => is_array($font) && is_string($font['familyName'] ?? null))
            ->map(fn (array $font): array => [
                'name' => $font['familyName'],
                'category' => (string) ($font['category'] ?? ''),
                'weights' => array_values(array_map('intval', (array) ($font['weights'] ?? [400]))),
            ])
            ->all());
    }
}
