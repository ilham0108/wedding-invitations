<?php

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\ReceivesStatusWebhooks;
use App\Services\WhatsApp\Data\DeliveryUpdate;
use App\Services\WhatsApp\Data\SendResult;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Meta WhatsApp Cloud API — POST /{version}/{phone-number-id}/messages.
 * Delivery status arrives only through the webhook (X-Hub-Signature-256).
 *
 * Note: Meta only delivers free-form text inside the 24-hour customer service window;
 * business-initiated messages outside it need a pre-approved Meta template.
 */
class MetaWhatsAppProvider extends AbstractProvider implements ReceivesStatusWebhooks
{
    /** Meta error codes that mean "slow down", not "never works". */
    private const RETRYABLE_ERRORS = [4, 80007, 130429, 131048, 131056];

    public function sendMessage(string $phone, string $message): SendResult
    {
        $version = $this->config->credential('api_version', 'v21.0');
        $url = "https://graph.facebook.com/{$version}/{$this->config->credential('phone_number_id')}/messages";

        return $this->attempt(
            fn (): Response => Http::timeout(self::TIMEOUT_SECONDS)
                ->withToken((string) $this->config->credential('access_token'))
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['preview_url' => true, 'body' => $message],
                ]),
            function (Response $response): SendResult {
                $body = $this->body($response);

                if ($response->successful() && isset($body['messages'][0]['id'])) {
                    return SendResult::sent((string) $body['messages'][0]['id'], $response->status(), $body);
                }

                $code = (int) ($body['error']['code'] ?? 0);

                return SendResult::failed(
                    (string) ($body['error']['message'] ?? __('Meta rejected the message.')),
                    $response->status(),
                    $body,
                    retryable: in_array($code, self::RETRYABLE_ERRORS, true),
                );
            },
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = $this->config->credential('app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');

        return $secret !== null
            && str_starts_with($signature, 'sha256=')
            && hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    /**
     * Webhook subscription handshake: echo hub.challenge when the verify token matches.
     */
    public function verificationChallenge(Request $request): ?string
    {
        $token = $this->config->credential('verify_token');

        return $token !== null
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->query('hub_verify_token'))
                ? (string) $request->query('hub_challenge')
                : null;
    }

    public function parseStatusWebhook(Request $request): array
    {
        $updates = [];

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    if (! isset($status['id'], $status['status'])) {
                        continue;
                    }

                    $updates[] = new DeliveryUpdate(
                        messageId: (string) $status['id'],
                        status: match ($status['status']) {
                            'delivered' => DeliveryUpdate::DELIVERED,
                            'read' => DeliveryUpdate::READ,
                            'failed' => DeliveryUpdate::FAILED,
                            default => DeliveryUpdate::SENT,
                        },
                        error: $status['errors'][0]['message'] ?? $status['errors'][0]['title'] ?? null,
                        occurredAt: isset($status['timestamp']) ? CarbonImmutable::createFromTimestamp((int) $status['timestamp']) : null,
                    );
                }
            }
        }

        return $updates;
    }
}
