<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\RequiresPaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Data\PaymentIntent;
use App\Services\Payment\Data\PaymentNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tripay closed payment (https://tripay.co.id). The channel (QRIS, VA, e-wallet, retail) is
 * chosen in our app; callbacks are signed with HMAC-SHA256 of the raw body and the private key,
 * and the status is then re-read from GET /transaction/detail.
 */
class TripayGateway implements PaymentGatewayInterface, RequiresPaymentMethod
{
    private const CHANNELS_CACHE_KEY = 'payment.tripay.channels';

    public function name(): string
    {
        return 'tripay';
    }

    public function paymentMethods(): array
    {
        // Cached as plain arrays (the cache store never unserializes objects).
        return Cache::remember(self::CHANNELS_CACHE_KEY, now()->addHour(), function (): array {
            $response = $this->client()->get('/merchant/payment-channel');

            if (! $response->successful() || $response->json('success') !== true) {
                throw new RuntimeException('Tripay payment channels could not be loaded: '.($response->json('message') ?? 'HTTP '.$response->status()));
            }

            return collect((array) $response->json('data'))
                ->filter(fn (array $channel): bool => (bool) ($channel['active'] ?? false))
                ->map(fn (array $channel): array => [
                    'code' => (string) $channel['code'],
                    'name' => (string) $channel['name'],
                    'group' => (string) ($channel['group'] ?? 'Other'),
                    'icon_url' => is_string($channel['icon_url'] ?? null) && str_starts_with($channel['icon_url'], 'https://') ? $channel['icon_url'] : null,
                ])
                ->values()
                ->all();
        });
    }

    public function createPayment(Order $order, Payment $payment): PaymentIntent
    {
        if ($payment->method === null) {
            throw new RuntimeException('Tripay needs a payment method.');
        }

        $order->loadMissing('plan:id,name', 'user:id,name,email,phone');
        $expiresAt = CarbonImmutable::now()->addHours((int) config('payment.payment_window_hours'));

        $response = $this->client()->post('/transaction/create', [
            'method' => $payment->method,
            'merchant_ref' => $payment->external_id,
            'amount' => $order->amount,
            'customer_name' => $order->user->name,
            'customer_email' => $order->user->email,
            'customer_phone' => (string) ($order->user->phone ?? ''),
            'order_items' => [['sku' => $order->plan->name, 'name' => __(':plan plan (:number)', ['plan' => $order->plan->name, 'number' => $order->number]), 'price' => $order->amount, 'quantity' => 1]],
            'return_url' => route('dashboard.subscription.orders.show', $order),
            'expired_time' => $expiresAt->getTimestamp(),
            'signature' => hash_hmac('sha256', $this->merchantCode().$payment->external_id.$order->amount, $this->privateKey()),
        ]);

        if (! $response->successful() || $response->json('success') !== true || ! is_string($response->json('data.checkout_url'))) {
            throw new RuntimeException('Tripay transaction could not be created: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        $data = (array) $response->json('data');

        return new PaymentIntent(
            reference: (string) $data['reference'],
            checkoutUrl: (string) $data['checkout_url'],
            expiresAt: isset($data['expired_time']) ? CarbonImmutable::createFromTimestamp((int) $data['expired_time']) : $expiresAt,
            payload: $this->safe($data),
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $signature = (string) $request->header('X-Callback-Signature');

        return $signature !== ''
            && $request->header('X-Callback-Event') === 'payment_status'
            && hash_equals(hash_hmac('sha256', $request->getContent(), $this->privateKey()), $signature);
    }

    public function parseWebhook(Request $request): ?PaymentNotification
    {
        $merchantRef = $request->input('merchant_ref');

        return is_string($merchantRef) ? $this->notification((array) $request->all()) : null;
    }

    public function fetchPayment(Payment $payment): ?PaymentNotification
    {
        if ($payment->gateway_reference === null) {
            return null;
        }

        try {
            $response = $this->client()->get('/transaction/detail', ['reference' => $payment->gateway_reference]);
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() && $response->json('success') === true
            ? $this->notification((array) $response->json('data'))
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notification(array $data): PaymentNotification
    {
        $status = match (strtoupper((string) ($data['status'] ?? ''))) {
            'PAID' => PaymentStatus::Paid,
            'EXPIRED' => PaymentStatus::Expired,
            'FAILED', 'REFUND' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new PaymentNotification(
            externalId: (string) ($data['merchant_ref'] ?? ''),
            status: $status,
            reference: isset($data['reference']) ? (string) $data['reference'] : null,
            // "amount" is the order amount; Tripay fees are reported separately.
            paidAmount: isset($data['amount']) ? (int) $data['amount'] : (isset($data['total_amount']) ? (int) $data['total_amount'] : null),
            method: isset($data['payment_name']) ? (string) $data['payment_name'] : ($data['payment_method'] ?? null),
            paidAt: ! empty($data['paid_at']) ? CarbonImmutable::createFromTimestamp((int) $data['paid_at']) : null,
            payload: $this->safe($data),
        );
    }

    private function client(): PendingRequest
    {
        $apiKey = (string) config('payment.gateways.tripay.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('TRIPAY_API_KEY is not configured.');
        }

        $baseUrl = config('payment.gateways.tripay.sandbox') ? 'https://tripay.co.id/api-sandbox' : 'https://tripay.co.id/api';

        return Http::baseUrl($baseUrl)->timeout(20)->acceptJson()->withToken($apiKey);
    }

    private function privateKey(): string
    {
        return (string) config('payment.gateways.tripay.private_key');
    }

    private function merchantCode(): string
    {
        return (string) config('payment.gateways.tripay.merchant_code');
    }

    /**
     * Keep only what helps support staff; drop personal data the gateway echoes back.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function safe(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'reference', 'merchant_ref', 'status', 'payment_method', 'payment_method_code', 'payment_name',
            'amount', 'fee_merchant', 'fee_customer', 'total_fee', 'amount_received', 'total_amount', 'paid_at', 'expired_time',
        ]));
    }
}
