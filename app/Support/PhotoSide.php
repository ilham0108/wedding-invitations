<?php

namespace App\Support;

/**
 * Couple layout "Photo beside" (person field photo_side): which side of the name each portrait sits on.
 * invitation.css (".inv-person-beside") places the columns; every design styles the portrait and text itself.
 */
final class PhotoSide
{
    public const SIDES = ['left', 'right'];

    /**
     * Gives every person a valid side. The side used to be one section field ("left", "right" or "alternate", which
     * starts left), so configurations saved before keep their look until they are saved again without it.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function perPerson(array $config): array
    {
        $sectionSide = $config['photo_side'] ?? null;
        unset($config['photo_side']);

        if (! is_array($config['people'] ?? null)) {
            return $config;
        }

        foreach (array_values(array_keys($config['people'])) as $index => $key) {
            if (is_array($config['people'][$key])) {
                $side = $config['people'][$key]['photo_side'] ?? null;
                $config['people'][$key]['photo_side'] = in_array($side, self::SIDES, true) ? $side : self::fromSection($sectionSide, $index);
            }
        }

        return $config;
    }

    public static function isRight(mixed $side): bool
    {
        return $side === 'right';
    }

    /**
     * Row classes for one person, or an empty string without a photo (the design's own centered layout).
     */
    public static function classes(mixed $side, bool $hasPhoto): string
    {
        if (! $hasPhoto) {
            return '';
        }

        return 'inv-person-beside'.(self::isRight($side) ? ' inv-person-beside--right' : '');
    }

    private static function fromSection(mixed $sectionSide, int $index): string
    {
        return match (true) {
            $sectionSide === 'right', $sectionSide === 'alternate' && $index % 2 === 1 => 'right',
            default => 'left',
        };
    }
}
