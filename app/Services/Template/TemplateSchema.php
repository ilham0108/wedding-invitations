<?php

namespace App\Services\Template;

use App\Services\Invitation\DecorationResolver;
use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\Ornaments;
use App\Services\Invitation\SectionRegistry;
use App\Services\Invitation\ThemeResolver;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The single gatekeeper for a template's configuration: section structure, theme,
 * decoration, defaults and sample content. Seeders, the admin builder and
 * template import all pass definitions through validate() before saving.
 */
class TemplateSchema
{
    /**
     * Bump when the definition format changes in a way older data cannot be read as-is.
     */
    public const VERSION = 1;

    public const BACKGROUNDS = ['default', 'muted', 'primary'];

    private const MAX_SECTIONS = 30;

    public function __construct(
        private SectionRegistry $registry,
        private ThemeResolver $themes,
        private DecorationResolver $decorations,
        private DesignRegistry $designs,
        private Ornaments $ornaments,
    ) {}

    /**
     * Validate a definition and return it in canonical form: every section is
     * {type, variant, background} with explicit values, and unknown keys are dropped.
     *
     * @param  array<string, mixed>  $definition
     * @return array{schema_version: int, design: string, schema: array{sections: array<int, array{type: string, variant: string, background: string}>}, theme: array<string, string>, decoration: array<string, string>, default_configuration: array<string, array<string, mixed>>, sample_content: array<string, array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public function validate(array $definition): array
    {
        $input = [
            'schema_version' => $definition['schema_version'] ?? self::VERSION,
            'design' => $definition['design'] ?? DesignRegistry::DEFAULT,
            'schema' => ['sections' => $this->normalizeSections($definition['schema']['sections'] ?? null, $definition['decoration']['ornaments'] ?? null)],
            'theme' => $definition['theme'] ?? [],
            'decoration' => $definition['decoration'] ?? [],
            'default_configuration' => $definition['default_configuration'] ?? [],
            'sample_content' => $definition['sample_content'] ?? [],
        ];

        Validator::make($input, $this->rules($input), [], $this->attributes($input))->validate();

        return [
            'schema_version' => (int) $input['schema_version'],
            'design' => $input['design'],
            'schema' => ['sections' => array_map(fn (array $section): array => array_filter([
                'type' => $section['type'],
                'variant' => $this->registry->resolveVariant($section['type'], $section['variant']),
                'background' => $section['background'] ?? 'default',
                // Only sections that have ornaments carry the key.
                'ornaments' => $this->ornaments->clean($section['ornaments'] ?? []) ?: null,
                'desktop_ornaments' => $this->ornaments->clean($section['desktop_ornaments'] ?? []) ?: null,
            ], fn (mixed $value): bool => $value !== null), $input['schema']['sections'])],
            'theme' => array_intersect_key($input['theme'], ['preset' => true, ...config('invitation.default_theme')]),
            'decoration' => $this->decoration($input['decoration']),
            'default_configuration' => $this->pruneConfiguration($input['default_configuration']),
            'sample_content' => $this->pruneConfiguration($input['sample_content']),
        ];
    }

    /**
     * Section entries may be a plain type string or an array with type, variant and background.
     *
     * @return array<int, array{type: mixed, variant: mixed, background: mixed}>|mixed
     */
    private function normalizeSections(mixed $sections, mixed $legacyOrnaments = null): mixed
    {
        if (! is_array($sections)) {
            return $sections;
        }

        // The first ornament format was one set for the whole template; place it on every section.
        $legacy = $this->ornaments->fromLegacy($legacyOrnaments);

        return array_values(array_map(fn (mixed $section): array => match (true) {
            is_string($section) => ['type' => $section, 'variant' => null, 'background' => null, 'ornaments' => $legacy],
            is_array($section) => [
                'type' => $section['type'] ?? null,
                'variant' => $section['variant'] ?? null,
                'background' => $section['background'] ?? null,
                'ornaments' => $section['ornaments'] ?? $legacy,
                'desktop_ornaments' => $section['desktop_ornaments'] ?? [],
            ],
            default => ['type' => null, 'variant' => null, 'background' => null, 'ornaments' => []],
        }, $sections));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, mixed>>
     */
    private function rules(array $input): array
    {
        $rules = [
            'schema_version' => ['required', 'integer', 'between:1,'.self::VERSION],
            'design' => ['required', 'string', Rule::in(array_keys($this->designs->all()))],
            'schema.sections' => ['required', 'array', 'min:1', 'max:'.self::MAX_SECTIONS],
            'theme' => ['array'],
            'theme.preset' => ['nullable', Rule::in(array_keys($this->themes->presets()))],
            'decoration' => ['array'],
            'default_configuration' => ['array', $this->knownSectionKeys()],
            'sample_content' => ['array', $this->knownSectionKeys()],
        ];

        foreach (is_array($input['schema']['sections']) ? $input['schema']['sections'] : [] as $index => $section) {
            $path = "schema.sections.{$index}";
            $rules["{$path}.type"] = ['required', 'string', Rule::in($this->registry->types())];
            $rules["{$path}.background"] = ['nullable', Rule::in(self::BACKGROUNDS)];
            $rules = array_merge($rules, $this->ornamentRules("{$path}.ornaments"), $this->ornamentRules("{$path}.desktop_ornaments"));

            if (is_string($section['type']) && $this->registry->has($section['type'])) {
                $rules["{$path}.variant"] = ['nullable', Rule::in($this->registry->variants($section['type']))];
            }
        }

        foreach (array_keys(config('invitation.default_theme')) as $key) {
            $rules["theme.{$key}"] = ['sometimes', $this->themeRule($key)];
        }

        foreach ($this->decorations->options() as $key => $options) {
            $rules["decoration.{$key}"] = ['sometimes', Rule::in($options)];
        }

        foreach (['default_configuration', 'sample_content'] as $group) {
            foreach ($this->knownTypesIn($input[$group]) as $type) {
                $rules["{$group}.{$type}"] = ['array'];
                $rules = array_merge($rules, $this->registry->rules($type, "{$group}.{$type}"));
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function attributes(array $input): array
    {
        $attributes = [];

        foreach (['default_configuration', 'sample_content'] as $group) {
            foreach ($this->knownTypesIn($input[$group]) as $type) {
                $attributes = array_merge($attributes, $this->registry->attributes($type, "{$group}.{$type}"));
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $decoration
     * @return array<string, mixed>
     */
    private function decoration(array $decoration): array
    {
        return array_intersect_key($decoration, config('invitation.default_decoration'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function ornamentRules(string $path): array
    {
        $options = config('invitation.ornament_options');

        return [
            $path => ['sometimes', 'array', 'max:'.$options['max_per_section']],
            "{$path}.*.image" => ['required', 'string', 'max:2048', 'regex:#^(https?://|/(?!/))#'],
            "{$path}.*.x" => ['sometimes', 'numeric', 'between:-50,150'],
            "{$path}.*.y" => ['sometimes', 'numeric', 'between:-50,150'],
            "{$path}.*.width" => ['sometimes', 'numeric', 'between:2,150'],
            "{$path}.*.anchor" => ['sometimes', Rule::in($options['anchors'])],
            "{$path}.*.flip" => ['sometimes', 'boolean'],
            "{$path}.*.layer" => ['sometimes', Rule::in($options['layers'])],
            "{$path}.*.enter" => ['sometimes', Rule::in($options['enters'])],
            "{$path}.*.duration" => ['sometimes', 'numeric', 'between:0.2,8'],
            "{$path}.*.delay" => ['sometimes', 'numeric', 'between:0,8'],
            "{$path}.*.motion" => ['sometimes', Rule::in($options['motions'])],
        ];
    }

    private function themeRule(string $key): mixed
    {
        return match (true) {
            str_ends_with($key, '_color') => 'regex:/^#[0-9a-fA-F]{6}$/',
            str_starts_with($key, 'font_') => Rule::in(array_keys(config('invitation.fonts'))),
            default => Rule::in(config("invitation.theme_options.{$key}", [])),
        };
    }

    /**
     * Configuration groups are keyed by section type; reject keys that are not section types.
     */
    private function knownSectionKeys(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $unknown = array_diff(array_keys((array) $value), $this->registry->types());

            if ($unknown !== []) {
                $fail(__('The :attribute contains unknown section types: :types.', ['types' => implode(', ', $unknown)]));
            }
        };
    }

    /**
     * @return array<int, string>
     */
    private function knownTypesIn(mixed $group): array
    {
        return is_array($group) ? array_values(array_intersect(array_keys($group), $this->registry->types())) : [];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, array<string, mixed>>
     */
    private function pruneConfiguration(array $group): array
    {
        return collect($group)
            ->map(fn (array $config, string $type): array => $this->registry->prune($type, $config))
            ->all();
    }
}
