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

// Unpaid gateway orders past their payment window: re-checked at the gateway, then activated or expired.
Schedule::command('payments:expire-stale')->hourly()->withoutOverlapping();

// Housekeeping. Retention per model is its PRUNE_AFTER_DAYS (guests and wishes 30 days after deletion,
// WhatsApp API logs and read notifications 90 days, audit log 365 days, expired cache rows right away).
// Database sessions are already cleared by Laravel's session lottery (config/session.php).
Schedule::command('model:prune')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])->dailyAt('03:15');
Schedule::command('auth:clear-resets')->dailyAt('03:20');
