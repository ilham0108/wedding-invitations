<?php

namespace App\Services\WhatsApp\Providers;

use App\Services\WhatsApp\Data\SendResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Fonnte (https://fonnte.com) — POST /send with the device token in the Authorization header.
 */
class FonnteProvider extends AbstractProvider
{
    private const ENDPOINT = 'https://api.fonnte.com/send';

    public function sendMessage(string $phone, string $message): SendResult
    {
        return $this->attempt(
            fn (): Response => Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Authorization' => (string) $this->config->credential('token')])
                ->asForm()
                ->post(self::ENDPOINT, [
                    'target' => $phone,
                    'message' => $message,
                    // Numbers are already international; stop Fonnte from rewriting a leading 0.
                    'countryCode' => '0',
                ]),
            function (Response $response): SendResult {
                $body = $this->body($response);

                if ($response->successful() && ($body['status'] ?? false) === true) {
                    $ids = (array) ($body['id'] ?? []);

                    return SendResult::sent(isset($ids[0]) ? (string) $ids[0] : null, $response->status(), $body);
                }

                $reason = (string) ($body['reason'] ?? $body['detail'] ?? '');

                return SendResult::failed($this->explain($reason), $response->status(), $body);
            },
        );
    }

    /**
     * Turn Fonnte's terse reasons into guidance the client can act on; the original reason is kept.
     */
    private function explain(string $reason): string
    {
        $hint = match (true) {
            str_contains(strtolower($reason), 'disconnected device') => __('Your WhatsApp device is not connected in Fonnte. Open the Fonnte dashboard → Device → Connect, then scan the QR code from WhatsApp → Linked devices.'),
            str_contains(strtolower($reason), 'invalid token') => __('The Fonnte token is not valid. Copy the device token again from the Fonnte dashboard.'),
            str_contains(strtolower($reason), 'quota') => __('Your Fonnte message quota has run out. Top up your Fonnte package.'),
            $reason === '' => __('Fonnte rejected the message.'),
            default => null,
        };

        return $hint !== null && $reason !== '' ? $hint.' ('.$reason.')' : ($hint ?? $reason);
    }
}
