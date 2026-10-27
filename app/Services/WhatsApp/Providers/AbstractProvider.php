<?php

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\Data\DeliveryUpdate;
use App\Services\WhatsApp\Data\SendResult;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * Shared transport behavior: timeouts, connection errors, retry classification, bulk sending.
 */
abstract class AbstractProvider implements WhatsAppProviderInterface
{
    protected const TIMEOUT_SECONDS = 15;

    public function __construct(protected WhatsAppProvider $config) {}

    /**
     * Send each message individually so every recipient gets its own message id and status.
     */
    public function sendBulk(array $messages): array
    {
        return array_map(fn (array $item): SendResult => $this->sendMessage($item['phone'], $item['message']), $messages);
    }

    public function getMessageStatus(string $providerMessageId): ?DeliveryUpdate
    {
        return null;
    }

    /**
     * @param  Closure(): Response  $request
     * @param  Closure(Response): SendResult  $interpret
     */
    protected function attempt(Closure $request, Closure $interpret): SendResult
    {
        try {
            $response = $request();
        } catch (ConnectionException) {
            return SendResult::failed(__('Could not reach the WhatsApp provider.'), retryable: true);
        }

        if ($response->status() === 429 || $response->serverError()) {
            return SendResult::failed(
                __('The WhatsApp provider is busy (HTTP :status).', ['status' => $response->status()]),
                $response->status(),
                $this->body($response),
                retryable: true,
            );
        }

        return $interpret($response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function body(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : ['raw' => mb_substr($response->body(), 0, 1000)];
    }
}
