<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

class RecordFailedLogin
{
    /**
     * Log failed attempts with a hashed email so credentials never reach the log.
     */
    public function handle(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');

        Log::warning('Authentication failed', [
            'guard' => $event->guard,
            'email_hash' => hash('sha256', strtolower($email)),
            'ip' => request()->ip(),
        ]);
    }
}
