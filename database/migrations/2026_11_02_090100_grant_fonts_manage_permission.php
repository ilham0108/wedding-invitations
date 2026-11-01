<?php

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Existing installations get the new "fonts.manage" permission for super admins without
     * re-running RolePermissionSeeder (which defines it for fresh installs).
     */
    public function up(): void
    {
        $role = Role::query()->where('slug', RoleSlug::SuperAdmin->value)->first();

        if ($role === null) {
            return;
        }

        $permission = Permission::query()->updateOrCreate(['slug' => 'fonts.manage'], ['name' => 'Manage fonts', 'group' => 'admin']);
        $role->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }

    public function down(): void
    {
        Permission::query()->where('slug', 'fonts.manage')->first()?->delete();
    }
};
