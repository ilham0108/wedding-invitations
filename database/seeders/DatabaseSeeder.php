<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SystemSettingSeeder::class,
            UserSeeder::class,
            PlanSeeder::class,
            TemplateCategorySeeder::class,
            InvitationTemplateSeeder::class,
            IllustrationSeeder::class,
            DemoInvitationSeeder::class,
            WhatsAppProviderSeeder::class,
            PageSeeder::class,
        ]);
    }
}
