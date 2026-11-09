<?php

namespace App\Services;

use App\Models\Font;
use App\Models\FontFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Every font themes can use: the built-in list in config/invitation.php (fonts) plus the fonts
 * an admin installed (Font: a Bunny Fonts family or uploaded files). Installed fonts are cached
 * as a plain array (the cache store refuses PHP objects) and memoized for the request.
 */
class FontRegistry
{
    private const CACHE_KEY = 'fonts.registry.v1';

    private const BUNNY_CSS = 'https://fonts.bunny.net/css?family=';

    public const CATEGORIES = ['serif', 'sans-serif', 'display', 'handwritten'];

    /** Takes over the Arabic letters of every font that has none; loaded by each design (config invitation.designs.*.fonts). */
    public const ARABIC = 'Amiri';

    /** CSS fallback stack per category, used for installed fonts. */
    public const FALLBACKS = [
        'serif' => 'Georgia, serif',
        'sans-serif' => 'system-ui, sans-serif',
        'display' => 'Georgia, serif',
        'handwritten' => 'cursive',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $fonts = null;

    /**
     * name => [category, fallback, source (builtin | bunny | upload), bunny (family parameter or null),
     * files ([url, format, weight, style]), active]. A built-in font wins over an installed one of the same name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->fonts ??= [...$this->installed(), ...collect(config('invitation.fonts'))
            ->map(fn (array $font): array => [...$font, 'source' => 'builtin', 'files' => [], 'active' => true])
            ->all()];
    }

    public function has(mixed $name): bool
    {
        return is_string($name) && isset($this->all()[$name]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * CSS font-family value of a known font.
     */
    public function stack(string $name): string
    {
        $arabic = $name === self::ARABIC ? '' : "'".self::ARABIC."', ";

        return "'{$name}', {$arabic}".$this->all()[$name]['fallback'];
    }

    /**
     * Weights the font actually loads (from its Bunny family parameter or its uploaded files),
     * so a chosen weight is never faked by the browser.
     *
     * @return array<int, int>
     */
    public function weights(string $name): array
    {
        $font = $this->get($name);

        if ($font === null) {
            return [];
        }

        $weights = $font['source'] === Font::SOURCE_UPLOAD
            ? array_column($font['files'], 'weight')
            : explode(',', (string) str($font['bunny'] ?? '')->after(':'));

        return collect($weights)->map(fn (mixed $weight): int => (int) $weight)->filter()->unique()->sort()->values()->all();
    }

    /**
     * Display names of the CSS font weights, for the weight pickers.
     *
     * @return array<string, string>
     */
    public function weightNames(): array
    {
        return ['100' => __('Thin'), '200' => __('Extra light'), '300' => __('Light'), '400' => __('Regular'), '500' => __('Medium'),
            '600' => __('Semibold'), '700' => __('Bold'), '800' => __('Extra bold'), '900' => __('Black')];
    }

    /**
     * Weight choices for a theme's heading and body font: the weights each font loads, plus the theme's
     * current value so it stays selected after the font changed.
     *
     * @param  array<string, mixed>  $theme
     * @return array{heading_weight: array<int, string>, body_weight: array<int, string>}
     */
    public function themeWeights(array $theme): array
    {
        $choices = fn (string $font, string $key): array => collect($this->weights((string) ($theme[$font] ?? '')))
            ->map(fn (int $weight): string => (string) $weight)
            ->push((string) ($theme[$key] ?? 'default'))
            ->reject(fn (string $weight): bool => $weight === 'default')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return ['heading_weight' => $choices('font_heading', 'heading_weight'), 'body_weight' => $choices('font_body', 'body_weight')];
    }

    /**
     * Fonts offered in the theme pickers, grouped by category: the active ones plus $current, so a
     * hidden font that a theme still uses stays selected.
     *
     * @param  array<int, string>  $current
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function forPicker(array $current = []): Collection
    {
        return collect($this->all())
            ->filter(fn (array $font, string $name): bool => $font['active'] || in_array($name, $current, true))
            ->map(fn (array $font, string $name): array => [...$font, 'name' => $name])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->groupBy('category');
    }

    /**
     * One stylesheet URL for the given fonts (unknown names are ignored). Only Bunny fonts:
     * the Bunny URL itself, exactly as before. With uploaded fonts: this app's stylesheet route,
     * versioned so browsers refetch it after a change.
     *
     * @param  array<int, string>  $names
     */
    public function stylesheetUrl(array $names): string
    {
        $names = $this->known($names);

        if (collect($names)->contains(fn (string $name): bool => $this->all()[$name]['source'] === Font::SOURCE_UPLOAD)) {
            return route('fonts.stylesheet', ['families' => implode('|', $names), 'v' => $this->version()]);
        }

        return self::BUNNY_CSS.$this->bunnyFamilies($names).'&display=swap';
    }

    /**
     * The CSS behind stylesheetUrl(): an @import of the Bunny fonts, then @font-face rules for
     * uploaded ones. Names are limited to letters, digits, spaces and hyphens, so they are safe in CSS.
     *
     * @param  array<int, string>  $names
     */
    public function stylesheet(array $names): string
    {
        $names = $this->known($names);
        $css = [];
        $bunny = $this->bunnyFamilies($names);

        if ($bunny !== '') {
            $css[] = '@import url("'.self::BUNNY_CSS.$bunny.'&display=swap");';
        }

        foreach ($names as $name) {
            foreach ($this->all()[$name]['files'] as $file) {
                $css[] = "@font-face { font-family: '{$name}'; src: url('{$file['url']}') format('{$file['format']}'); "
                    ."font-weight: {$file['weight']}; font-style: {$file['style']}; font-display: swap; }";
            }
        }

        return implode("\n", $css)."\n";
    }

    /**
     * Changes whenever an installed font or file changes.
     */
    public function version(): string
    {
        return substr(md5((string) json_encode($this->installed())), 0, 10);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);

        if (app()->resolved(self::class)) {
            app(self::class)->fonts = null;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function installed(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Font::query()
            ->with('files')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Font $font): array => [$font->name => [
                'category' => $font->category,
                'fallback' => $font->fallback,
                'source' => $font->source,
                'bunny' => $font->bunny_family,
                'files' => $font->files->map(fn (FontFile $file): array => [
                    'url' => $file->url(),
                    'format' => $file->format,
                    'weight' => $file->weight,
                    'style' => $file->style,
                ])->all(),
                'active' => $font->is_active,
            ]])
            ->all());
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function known(array $names): array
    {
        return collect($names)->filter(fn (mixed $name): bool => $this->has($name))->unique()->values()->all();
    }

    /**
     * @param  array<int, string>  $names
     */
    private function bunnyFamilies(array $names): string
    {
        return collect($names)
            ->map(fn (string $name): ?string => $this->all()[$name]['bunny'] ?? null)
            ->filter()
            ->implode('|');
    }
}
