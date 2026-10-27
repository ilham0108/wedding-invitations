<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled WhatsApp campaigns (rules.md section 45). Requires `php artisan schedule:work` or a cron entry.
Schedule::command('whatsapp:dispatch-scheduled')->everyMinute()->withoutOverlapping();

// Plan expiry reminders and expirations. Access already ends on time without it (limits check ends_at);
// this only sends the notifications and marks rows as expired.
Schedule::command('subscriptions:process-expirations')->hourly()->withoutOverlapping();
