<?php

namespace App\Enums;

enum TemplateStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return __(match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        });
    }
}
