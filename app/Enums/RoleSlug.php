<?php

namespace App\Enums;

enum RoleSlug: string
{
    case SuperAdmin = 'super_admin';
    case Client = 'client';

    public function label(): string
    {
        return __(match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Client => 'Client',
        });
    }
}
