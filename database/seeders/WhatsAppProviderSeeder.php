<?php

namespace Database\Seeders;

use App\Enums\WhatsAppDriver;
use App\Models\WhatsAppProvider;
use Illuminate\Database\Seeder;

/**
 * Local development only: an active "log" provider so campaigns can be tried without
 * real credentials. Messages are written to storage/logs and never reach WhatsApp.
 */
class WhatsAppProviderSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || WhatsAppProvider::query()->exists()) {
            return;
        }

        WhatsAppProvider::query()->create([
            'driver' => WhatsAppDriver::Log,
            'name' => 'Development log',
            'credentials' => [],
            'rate_per_minute' => 60,
            'is_active' => true,
        ]);
    }
}
