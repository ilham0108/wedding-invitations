<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission slugs grouped by area, as defined in rules.md section 7.
     *
     * @var array<string, array<string, string>>
     */
    private const PERMISSIONS = [
        'admin' => [
            'users.manage' => 'Manage users',
            'users.suspend' => 'Suspend accounts',
            'invitations.manage-any' => 'Manage all invitations',
            'templates.manage' => 'Manage templates',
            'illustrations.manage' => 'Manage illustrations',
            'music.manage' => 'Manage music',
            'template-categories.manage' => 'Manage template categories',
            'plans.manage' => 'Manage packages',
            'subscriptions.manage' => 'Manage subscriptions',
            'orders.manage' => 'Manage orders',
            'payments.manage' => 'Manage payments',
            'whatsapp-providers.manage' => 'Manage WhatsApp providers',
            'settings.manage' => 'Manage system settings',
            'cms.manage' => 'Manage CMS content',
            'analytics.view-any' => 'View platform analytics',
            'audit-logs.view' => 'View audit logs',
        ],
        'client' => [
            'profile.manage' => 'Manage own profile',
            'invitations.create' => 'Create invitations',
            'invitations.manage-own' => 'Manage own invitations',
            'templates.choose' => 'Choose templates',
            'invitations.customize' => 'Customize invitations',
            'guests.manage' => 'Manage guests',
            'rsvp.manage' => 'Manage RSVP',
            'whatsapp-campaigns.manage' => 'Manage WhatsApp campaigns',
            'analytics.view-own' => 'View own analytics',
            'subscription.manage-own' => 'Manage own subscription',
            'domains.manage-own' => 'Manage own domain',
        ],
    ];

    public function run(): void
    {
        $permissionIds = [];

        foreach (self::PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                $permissionIds[$group][] = Permission::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'group' => $group],
                )->getKey();
            }
        }

        $superAdmin = Role::query()->updateOrCreate(
            ['slug' => RoleSlug::SuperAdmin->value],
            ['name' => RoleSlug::SuperAdmin->label(), 'description' => 'Full access to the platform.'],
        );

        $client = Role::query()->updateOrCreate(
            ['slug' => RoleSlug::Client->value],
            ['name' => RoleSlug::Client->label(), 'description' => 'Customer who creates and manages invitations.'],
        );

        $superAdmin->permissions()->sync(array_merge($permissionIds['admin'], $permissionIds['client']));
        $client->permissions()->sync($permissionIds['client']);
    }
}
