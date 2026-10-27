<?php

namespace App\Services\Invitation;

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
        return in_array($value, $this->options()[$key] ?? [], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function options(): array
    {
        return config('invitation.decoration_options');
    }
}
