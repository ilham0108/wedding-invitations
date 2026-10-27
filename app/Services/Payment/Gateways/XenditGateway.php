<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Data\PaymentIntent;
use App\Services\Payment\Data\PaymentNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Xendit Invoice API (v2). The customer picks VA, QRIS, e-wallet, or retail on Xendit's page.
 * Callbacks carry the "x-callback-token" header; status is then re-read from the API.
 */
class XenditGateway implements PaymentGatewayInterface
{
    private const BASE_URL = 'https://api.xendit.co';

    public function name(): string
    {
        return 'xendit';
    }

    public function createPayment(Order $order, Payment $payment): PaymentIntent
    {
        $order->loadMissing('plan:id,name', 'user:id,name,email');

        $response = $this->client()->post('/v2/invoices', [
            'external_id' => $payment->external_id,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'description' => __(':plan plan (:number)', ['plan' => $order->plan->name, 'number' => $order->number]),
            'invoice_duration' => (int) config('payment.payment_window_hours') * 3600,
            'payer_email' => $order->user->email,
            'customer' => ['given_names' => $order->user->name, 'email' => $order->user->email],
            'items' => [['name' => $order->plan->name, 'quantity' => 1, 'price' => $order->amount]],
            'success_redirect_url' => route('dashboard.subscription.orders.show', $order),
            'failure_redirect_url' => route('dashboard.subscription.orders.show', $order),
        ]);

        if (! $response->successful() || ! is_string($response->json('invoice_url'))) {
            throw new RuntimeException('Xendit invoice could not be created: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return new PaymentIntent(
            reference: (string) $response->json('id'),
            checkoutUrl: (string) $response->json('invoice_url'),
            expiresAt: $this->date($response->json('expiry_date')),
            payload: $this->safe((array) $response->json()),
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $token = config('payment.gateways.xendit.callback_token');

        return is_string($token) && $token !== ''
            && hash_equals($token, (string) $request->header('x-callback-token'));
    }

    public function parseWebhook(Request $request): ?PaymentNotification
    {
        $externalId = $request->input('external_id');

        return is_string($externalId) ? $this->notification((array) $request->all()) : null;
    }

    public function fetchPayment(Payment $payment): ?PaymentNotification
    {
        if ($payment->gateway_reference === null) {
            return null;
        }

        try {
            $response = $this->client()->get('/v2/invoices/'.rawurlencode($payment->gateway_reference));
        } catch (ConnectionException) {
            return null;
        }

        return $response->successful() ? $this->notification((array) $response->json()) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notification(array $data): PaymentNotification
    {
        $status = match (strtoupper((string) ($data['status'] ?? ''))) {
            'PAID', 'SETTLED' => PaymentStatus::Paid,
            'EXPIRED' => PaymentStatus::Expired,
            'FAILED' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new PaymentNotification(
            externalId: (string) ($data['external_id'] ?? ''),
            status: $status,
            reference: isset($data['id']) ? (string) $data['id'] : null,
            paidAmount: isset($data['paid_amount']) ? (int) round((float) $data['paid_amount']) : null,
            method: trim(implode(' ', array_filter([$data['payment_method'] ?? null, $data['payment_channel'] ?? null]))) ?: null,
            paidAt: $this->date($data['paid_at'] ?? null),
            payload: $this->safe($data),
        );
    }

    private function client(): PendingRequest
    {
        $secret = (string) config('payment.gateways.xendit.secret_key');

        if ($secret === '') {
            throw new RuntimeException('XENDIT_SECRET_KEY is not configured.');
        }

        return Http::baseUrl(self::BASE_URL)->timeout(20)->acceptJson()->withBasicAuth($secret, '');
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
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
            'id', 'external_id', 'status', 'amount', 'paid_amount', 'currency',
            'payment_method', 'payment_channel', 'bank_code', 'paid_at', 'expiry_date', 'created', 'updated',
        ]));
    }
}
