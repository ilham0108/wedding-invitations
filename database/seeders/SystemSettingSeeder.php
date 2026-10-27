<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'general', 'key' => 'site_name', 'value' => config('app.name'), 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'support_email', 'value' => 'support@example.com', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'default_timezone', 'value' => 'Asia/Jakarta', 'type' => 'string', 'is_public' => false],
            ['group' => 'auth', 'key' => 'registration_enabled', 'value' => '1', 'type' => 'boolean', 'is_public' => false],
        ];

        foreach ($settings as $setting) {
            SystemSetting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
