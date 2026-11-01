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
     * @param  array<string, mixed>  ...$layers
     * @return array<string, string>
     */
    public function resolve(array ...$layers): array
    {
        $decoration = config('invitation.default_decoration');

        foreach ($layers as $layer) {
            foreach (array_intersect_key($layer, $decoration) as $key => $value) {
                if ($this->isValid($key, $value)) {
                    $decoration[$key] = $value;
                }
            }
        }

        return $decoration;
    }

    public function isValid(string $key, mixed $value): bool
    {
        // The heading ornament image is an illustration ULID (never a URL), or empty.
        if ($key === 'heading_ornament_image') {
            return $value === '' || (is_string($value) && Str::isUlid($value));
        }

        return in_array($value, $this->options()[$key] ?? [], true);
    }

    /**
     * The decoration of a rendered page: resolve() plus "heading_ornament_url". A library image that is
     * gone turns the heading ornament back into the design's own.
     *
     * @param  array<string, mixed>  ...$layers
     * @return array<string, string>
     */
    public function forPage(array ...$layers): array
    {
        $decoration = $this->resolve(...$layers);
        $url = $this->headingOrnamentUrl($decoration);

        if ($url === null && $decoration['heading_ornament'] === 'image') {
            $decoration['heading_ornament'] = 'design';
        }

        return [...$decoration, 'heading_ornament_url' => (string) $url];
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
