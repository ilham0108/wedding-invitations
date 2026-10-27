<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Data\PaymentIntent;
use App\Services\Payment\Data\PaymentNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Bank transfer confirmed by an admin (PaymentService::confirmManually). No checkout page, no webhook.
 */
class ManualGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'manual';
    }

    public function createPayment(Order $order, Payment $payment): PaymentIntent
    {
        return new PaymentIntent(
            reference: $payment->external_id,
            checkoutUrl: null,
            expiresAt: CarbonImmutable::now()->addHours((int) config('payment.payment_window_hours')),
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): ?PaymentNotification
    {
        return null;
    }

    public function fetchPayment(Payment $payment): ?PaymentNotification
    {
        return null;
    }
}
