<?php

namespace App\Support;

/**
 * Whether an editor field applies right now (livewire.partials.section-field, livewire.partials.section-fields).
 * Field definitions can limit themselves to layouts ("variants"), to a design feature ("feature"), to a sibling field
 * being empty ("hidden_when") or set to a value ("shown_when"); a focal point ("focus") needs its photo.
 */
final class FieldVisibility
{
    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $config  The values being edited.
     * @param  string  $path  The field's path in $config, e.g. "people.0.name".
     * @param  array<int, string>  $features  The design's features.
     */
    public static function shown(array $field, array $config, string $path, ?string $variant, array $features): bool
    {
        $sibling = fn (string $key): mixed => data_get($config, preg_replace('/[^.]+$/', $key, $path));

        return (! isset($field['variants']) || in_array($variant, $field['variants'], true))
            && (! isset($field['feature']) || in_array($field['feature'], $features, true))
            && (! isset($field['hidden_when']) || blank($sibling($field['hidden_when'])))
            && (! isset($field['shown_when']) || $sibling($field['shown_when'][0]) === $field['shown_when'][1])
            && ($field['type'] !== 'focus' || filled($sibling($field['image'])));
    }
}
