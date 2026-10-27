<?php

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\ReceivesStatusWebhooks;
use App\Services\WhatsApp\Data\DeliveryUpdate;
use App\Services\WhatsApp\Data\SendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Twilio Programmable Messaging for WhatsApp. Status arrives via StatusCallback
 * (X-Twilio-Signature) and can also be polled.
 *
 * Note: like Meta, business-initiated messages outside the 24-hour window need an approved template.
 */
class TwilioProvider extends AbstractProvider implements ReceivesStatusWebhooks
{
    public function sendMessage(string $phone, string $message): SendResult
    {
        return $this->attempt(
            fn (): Response => $this->client()->asForm()->post($this->messagesUrl().'.json', [
                'From' => 'whatsapp:+'.ltrim((string) $this->config->credential('from'), '+'),
                'To' => 'whatsapp:+'.$phone,
                'Body' => $message,
                'StatusCallback' => $this->config->isPlatform()
                    ? route('webhooks.whatsapp', ['driver' => 'twilio'])
                    : route('webhooks.whatsapp.connection', ['driver' => 'twilio', 'provider' => $this->config->ulid]),
            ]),
            function (Response $response): SendResult {
                $body = $this->body($response);

                if ($response->successful() && isset($body['sid'])) {
                    return SendResult::sent((string) $body['sid'], $response->status(), $body);
                }

                return SendResult::failed((string) ($body['message'] ?? __('Twilio rejected the message.')), $response->status(), $body);
            },
        );
    }

    public function getMessageStatus(string $providerMessageId): ?DeliveryUpdate
    {
        try {
            $response = $this->client()->get($this->messagesUrl().'/'.rawurlencode($providerMessageId).'.json');
        } catch (ConnectionException) {
            return null;
        }

        $status = $response->successful() ? $response->json('status') : null;

        return is_string($status) ? new DeliveryUpdate($providerMessageId, $this->mapStatus($status), $response->json('error_message')) : null;
    }

    public function verifyWebhook(Request $request): bool
    {
        $token = $this->config->credential('auth_token');
        $signature = (string) $request->header('X-Twilio-Signature');

        if ($token === null || $signature === '') {
            return false;
        }

        $params = $request->post();
        ksort($params);
        $payload = $request->fullUrl();

        foreach ($params as $key => $value) {
            $payload .= $key.$value;
        }

        return hash_equals(base64_encode(hash_hmac('sha1', $payload, $token, true)), $signature);
    }

    public function parseStatusWebhook(Request $request): array
    {
        $sid = $request->input('MessageSid');
        $status = $request->input('MessageStatus');

        return is_string($sid) && is_string($status)
            ? [new DeliveryUpdate($sid, $this->mapStatus($status), $request->input('ErrorMessage') ?? $request->input('ErrorCode'))]
            : [];
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'delivered' => DeliveryUpdate::DELIVERED,
            'read' => DeliveryUpdate::READ,
            'failed', 'undelivered' => DeliveryUpdate::FAILED,
            default => DeliveryUpdate::SENT,
        };
    }

    private function client(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SECONDS)
            ->withBasicAuth((string) $this->config->credential('account_sid'), (string) $this->config->credential('auth_token'));
    }

    private function messagesUrl(): string
    {
        return 'https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode((string) $this->config->credential('account_sid')).'/Messages';
    }
}
