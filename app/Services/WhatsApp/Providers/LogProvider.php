<?php

namespace App\Services\WhatsApp\Providers;

use App\Services\WhatsApp\Data\SendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development driver: writes the message to the log instead of sending it. Nothing reaches WhatsApp.
 */
class LogProvider extends AbstractProvider
{
    public function sendMessage(string $phone, string $message): SendResult
    {
        Log::info('WhatsApp message (log driver, not sent)', [
            'phone' => Str::mask($phone, '*', 4, -3),
            'length' => mb_strlen($message),
        ]);

        return SendResult::sent('log-'.Str::lower((string) Str::ulid()));
    }
}
