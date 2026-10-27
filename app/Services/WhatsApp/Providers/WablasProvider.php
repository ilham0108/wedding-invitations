<?php

namespace App\Services\WhatsApp\Providers;

use App\Services\WhatsApp\Data\SendResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Wablas (https://wablas.com) — POST {server}/api/send-message. Newer accounts sign
 * requests with "token.secret_key"; older ones with the token alone.
 */
class WablasProvider extends AbstractProvider
{
    public function sendMessage(string $phone, string $message): SendResult
    {
        $token = (string) $this->config->credential('token');
        $secret = $this->config->credential('secret_key');

        return $this->attempt(
            fn (): Response => Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Authorization' => $secret ? $token.'.'.$secret : $token])
                ->asForm()
                ->post(rtrim((string) $this->config->credential('base_url'), '/').'/api/send-message', [
                    'phone' => $phone,
                    'message' => $message,
                ]),
            function (Response $response): SendResult {
                $body = $this->body($response);

                if ($response->successful() && ($body['status'] ?? false) === true) {
                    $messageId = $body['data']['messages'][0]['id'] ?? $body['data']['id'] ?? null;

                    return SendResult::sent($messageId !== null ? (string) $messageId : null, $response->status(), $body);
                }

                return SendResult::failed((string) ($body['message'] ?? __('Wablas rejected the message.')), $response->status(), $body);
            },
        );
    }
}
