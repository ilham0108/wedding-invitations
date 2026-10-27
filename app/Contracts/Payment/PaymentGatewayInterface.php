<?php

namespace App\Contracts\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Data\PaymentIntent;
use App\Services\Payment\Data\PaymentNotification;
use Illuminate\Http\Request;

/**
 * Payment gateway abstraction (rules.md section 49). Gateways only talk to the outside world;
 * PaymentService decides what a result means for orders and subscriptions.
 */
interface PaymentGatewayInterface
{
    public function name(): string;

    /**
     * Start a payment at the gateway and return where the customer should pay.
     */
    public function createPayment(Order $order, Payment $payment): PaymentIntent;

    /**
     * Check the webhook's authenticity with the gateway secret. Unverified requests must be rejected.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * The payment the webhook is about. Its status is a hint only: callers re-check with fetchPayment().
     */
    public function parseWebhook(Request $request): ?PaymentNotification;

    /**
     * Authoritative status straight from the gateway, or null when it cannot be determined right now.
     */
    public function fetchPayment(Payment $payment): ?PaymentNotification;
}
