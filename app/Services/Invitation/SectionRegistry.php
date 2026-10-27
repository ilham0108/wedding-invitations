<?php

namespace App\Services\Invitation;

use InvalidArgumentException;

/**
 * Read-only access to the section types defined in config/invitation.php.
 */
class SectionRegistry
{
    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_keys(config('invitation.sections'));
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, config('invitation.sections'));
    }

    /**
     * @return array{label: string, icon: string, variants: array<int, string>, defaults: array<string, mixed>}
     */
    public function definition(string $type): array
    {
        if (! $this->has($type)) {
            throw new InvalidArgumentException("Unknown invitation section type [{$type}].");
        }

        return config("invitation.sections.{$type}");
    }

    public function label(string $type): string
    {
        return $this->definition($type)['label'];
    }

    /**
     * @return array<int, string>
     */
    public function variants(string $type): array
    {
        return $this->definition($type)['variants'];
    }

    /**
     * Return the requested variant when it exists, otherwise the section's first variant.
     */
    public function resolveVariant(string $type, ?string $variant): string
    {
        $variants = $this->variants($type);

        return in_array($variant, $variants, true) ? $variant : $variants[0];
    }

    /**
     * Default configuration with its copy translated to the current locale
     * (config holds the English source strings, also inside default list items).
     *
     * @return array<string, mixed>
     */
    public function defaults(string $type): array
    {
        $common = array_fill_keys(array_column($this->commonFields($type), 'key'), null);

        return $this->translateDefaults($this->fields($type), [...$this->definition($type)['defaults'], ...$common]);
    }

    /**
     * Translate text and textarea values; option keys, URLs and nested list items keep their type.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function translateDefaults(array $fields, array $values): array
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field['key'], $values)) {
                continue;
            }

            $value = $values[$field['key']];

            $values[$field['key']] = match (true) {
                in_array($field['type'], ['text', 'textarea'], true) && is_string($value) && $value !== '' => __($value),
                $field['type'] === 'repeater' && is_array($value) => array_map(
                    fn (mixed $item): mixed => is_array($item) ? $this->translateDefaults($field['fields'], $item) : $item,
                    $value,
                ),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * Human-readable validation attribute names derived from the field labels.
     *
     * @return array<string, string>
     */
    public function attributes(string $type, string $prefix = 'config'): array
    {
        return $this->attributesForFields($this->fields($type), $prefix);
    }

    /**
     * Each variant is its own Blade file: resources/views/invitation/sections/{type}/{variant}.blade.php.
     * Unknown variants fall back to the section's first variant.
     */
    public function view(string $type, ?string $variant = null): string
    {
        return 'invitation.sections.'.str_replace('_', '-', $type).'.'.$this->resolveVariant($type, $variant);
    }

    /**
     * Editor field definitions for a section type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(string $type): array
    {
        $this->definition($type);

        return [...config("invitation.fields.{$type}", []), ...$this->commonFields($type)];
    }

    /**
     * Fields clients edit on their invitation: every field except the template-only ones ("admin_only").
     *
     * @return array<int, array<string, mixed>>
     */
    public function clientFields(string $type): array
    {
        return array_values(array_filter($this->fields($type), fn (array $field): bool => empty($field['admin_only'])));
    }

    /**
     * Keys of the top-level fields only the template builder may change.
     *
     * @return array<int, string>
     */
    public function adminOnlyKeys(string $type): array
    {
        return array_column(array_filter($this->fields($type), fn (array $field): bool => ! empty($field['admin_only'])), 'key');
    }

    /**
     * Fields shared by every section type (config "common_fields"), minus the types they exclude.
     *
     * @return array<int, array<string, mixed>>
     */
    public function commonFields(string $type): array
    {
        return collect(config('invitation.common_fields', []))
            ->reject(fn (array $field): bool => in_array($type, $field['except'] ?? [], true))
            ->map(fn (array $field): array => collect($field)->except('except')->all())
            ->values()
            ->all();
    }

    /**
     * Validation rules derived from the field definitions.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $type, string $prefix = 'config'): array
    {
        return $this->rulesForFields($this->fields($type), $prefix);
    }

    /**
     * Keep only known fields (recursively) and cast them to their declared types.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitize(string $type, array $input): array
    {
        return $this->sanitizeFields($this->fields($type), $input, $this->defaults($type));
    }

    /**
     * Drop unknown keys (recursively) but keep the given values as they are and add no defaults.
     * Used for partial configuration such as template defaults and sample content.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function prune(string $type, array $input): array
    {
        return $this->pruneFields($this->fields($type), $input);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function pruneFields(array $fields, array $input): array
    {
        $clean = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field['key'], $input)) {
                continue;
            }

            $value = $input[$field['key']];

            $clean[$field['key']] = $field['type'] === 'repeater' && is_array($value)
                ? array_values(array_map(
                    fn (mixed $item): mixed => is_array($item) ? $this->pruneFields($field['fields'], $item) : $item,
                    $value,
                ))
                : $value;
        }

        return $clean;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<int, mixed>>
     */
    private function rulesForFields(array $fields, string $prefix): array
    {
        $rules = [];
        $mediaPath = 'regex:#^(https?://|/)#';

        foreach ($fields as $field) {
            $path = "{$prefix}.{$field['key']}";
            $max = $field['max'] ?? null;

            match ($field['type']) {
                'text' => $rules[$path] = ['nullable', 'string', 'max:255'],
                'textarea' => $rules[$path] = ['nullable', 'string', 'max:2000'],
                'toggle' => $rules[$path] = ['boolean'],
                'select' => $rules[$path] = ['nullable', 'in:'.implode(',', array_keys($field['options']))],
                'date' => $rules[$path] = ['nullable', 'date_format:Y-m-d'],
                'time' => $rules[$path] = ['nullable', 'date_format:H:i'],
                'url' => $rules[$path] = ['nullable', 'url:http,https', 'max:2048'],
                'number' => $rules[$path] = ['nullable', 'numeric', 'between:'.($field['min'] ?? PHP_INT_MIN).','.($field['max'] ?? PHP_INT_MAX)],
                'image' => $rules[$path] = ['nullable', 'string', 'max:2048', $mediaPath],
                default => null,
            };

            if ($field['type'] === 'images') {
                $rules[$path] = ['array', 'max:'.($max ?? 30)];
                $rules["{$path}.*"] = ['string', 'max:2048', $mediaPath];
            }

            if ($field['type'] === 'colors') {
                $rules[$path] = ['array', 'max:'.($max ?? 8)];
                $rules["{$path}.*"] = ['string', 'regex:/^#[0-9a-fA-F]{6}$/'];
            }

            if ($field['type'] === 'repeater') {
                $rules[$path] = ['array', 'max:'.($max ?? 20)];
                $rules = array_merge($rules, $this->rulesForFields($field['fields'], "{$path}.*"));
            }
        }

        return $rules;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, string>
     */
    private function attributesForFields(array $fields, string $prefix): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            $path = "{$prefix}.{$field['key']}";
            $attributes[$path] = mb_strtolower(__($field['label']));

            if (in_array($field['type'], ['images', 'colors'], true)) {
                $attributes["{$path}.*"] = mb_strtolower(__($field['label']));
            }

            if ($field['type'] === 'repeater') {
                $attributes = array_merge($attributes, $this->attributesForFields($field['fields'], "{$path}.*"));
            }
        }

        return $attributes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function sanitizeFields(array $fields, array $input, array $defaults = []): array
    {
        $clean = [];

        foreach ($fields as $field) {
            $value = $input[$field['key']] ?? $defaults[$field['key']] ?? null;

            $clean[$field['key']] = match ($field['type']) {
                'toggle' => (bool) $value,
                'number' => is_numeric($value) ? (float) $value : null,
                'images', 'colors' => array_values(array_filter((array) $value, 'is_string')),
                'repeater' => array_values(array_map(
                    fn (mixed $item): array => $this->sanitizeFields($field['fields'], is_array($item) ? $item : []),
                    (array) $value,
                )),
                default => is_scalar($value) ? trim((string) $value) : '',
            };
        }

        return $clean;
    }
}
