<?php

namespace App\Enums;

/**
 * Where an illustration fits. Editor image fields declare which category they accept
 * (config/invitation.php "illustrations" key), so the picker only shows suitable art.
 */
enum IllustrationCategory: string
{
    case Cover = 'cover';
    case Person = 'person';
    case Story = 'story';
    case Gallery = 'gallery';
    // Transparent decorations placed on the edges of every section (template decoration "ornaments").
    case Ornament = 'ornament';
    // Transparent frames with an opening, laid over portraits (couple "photo_frame").
    case Frame = 'frame';

    public function label(): string
    {
        return __(match ($this) {
            self::Cover => 'Cover backgrounds',
            self::Person => 'People',
            self::Story => 'Story moments',
            self::Gallery => 'Gallery art',
            self::Ornament => 'Ornaments',
            self::Frame => 'Photo frames',
        });
    }
}
