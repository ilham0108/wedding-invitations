<?php

namespace App\Services\Payment;

use App\Contracts\Payment\RequiresPaymentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentCompleted;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlanPrice;
use App\Models\User;
use App\Notifications\PaymentFailed;
use App\Notifications\PaymentSucceeded;
use App\Services\AuditLogger;
use App\Services\Payment\Data\PaymentNotification;
use App\Services\Subscription\SubscriptionLimitService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Order lifecycle (rules.md section 49): create order → gateway → webhook → verify → update payment → activate.
 * Never trusts a browser redirect or an unverified callback for payment status.
 */
class PaymentService
{
    /**
     * Gateways may report a payment made at the last minute a little late.
     */
    private const EXPIRY_GRACE_MINUTES = 60;

    public function __construct(
        private PaymentManager $gateways,
        private SubscriptionService $subscriptions,
        private SubscriptionLimitService $limits,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ValidationException
     */
    public function checkout(User $user, PlanPrice $price, ?string $method = null): Order
    {
        $price->loadMissing('plan');
        $plan = $price->plan;

        if (! $price->is_active || ! $plan->is_active || $plan->is_free) {
            throw ValidationException::withMessages(['price' => __('This plan is not available.')]);
        }

        $current = $this->limits->currentSubscription($user);

        if ($current !== null && $current->plan->rank > $plan->rank) {
            throw ValidationException::withMessages(['price' => __('You already have a higher plan. You can switch after it ends.')]);
        }

        $gateway = $this->gateways->default();

        if ($gateway instanceof RequiresPaymentMethod) {
            $available = array_column($gateway->paymentMethods(), 'code');

            if ($method === null || ! in_array($method, $available, true)) {
                throw ValidationException::withMessages(['method' => __('Choose a payment method.')]);
            }
        } else {
            $method = null;
        }

        // Reuse an unpaid checkout for the same price (and method) instead of piling up invoices.
        $existing = $user->orders()
            ->where('plan_price_id', $price->id)
            ->where('status', OrderStatus::Pending)
            ->where('expires_at', '>', now()->addMinutes(10))
            ->with('latestPayment')
            ->latest()
            ->first();

        if ($existing !== null
            && $existing->latestPayment?->status === PaymentStatus::Pending
            && $existing->latestPayment->gateway === $gateway->name()
            && $existing->latestPayment->method === $method) {
            return $existing;
        }

        // The gateway is called after the commit: an HTTP call inside the transaction would hold it open
        // for seconds, and an invoice created right before a failed commit would belong to no order.
        [$order, $payment] = DB::transaction(function () use ($user, $price, $plan, $gateway, $method): array {
            $order = new Order([
                'number' => $this->orderNumber(),
                'billing_period' => $price->billing_period,
                'duration_days' => $price->duration_days,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'status' => OrderStatus::Pending,
            ]);
            $order->user()->associate($user);
            $order->plan()->associate($plan);
            $order->price()->associate($price);
            $order->save();

            $payment = $order->payments()->create([
                'gateway' => $gateway->name(),
                'external_id' => $order->number.'-'.Str::upper(Str::random(4)),
                'status' => PaymentStatus::Pending,
                'amount' => $order->amount,
                'method' => $method,
            ]);

            return [$order, $payment];
        });

        try {
            $intent = $gateway->createPayment($order, $payment);
        } catch (Throwable $exception) {
            // The customer never got a checkout link, so nothing can be paid on this order.
            DB::transaction(function () use ($order, $payment): void {
                $payment->update(['status' => PaymentStatus::Failed]);
                $order->update(['status' => OrderStatus::Failed]);
            });

            throw $exception;
        }

        // Only these columns change, so a webhook that already moved the status is never overwritten.
        $payment->update([
            'gateway_reference' => $intent->reference,
            'checkout_url' => $intent->checkoutUrl,
            'expires_at' => $intent->expiresAt,
            'payload' => $intent->payload ?: null,
        ]);
        $order->update(['expires_at' => $intent->expiresAt]);

        $this->auditLogger->record('order.created', $user, $order, ['plan' => $plan->slug, 'amount' => $order->amount, 'gateway' => $gateway->name()]);

        return $order;
    }

    /**
     * Re-read the payment from the gateway and apply it. Used by webhooks and the order status page.
     */
    public function sync(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        $notification = $this->gateways->gateway($payment->gateway)->fetchPayment($payment);

        return $notification !== null ? $this->apply($payment, $notification) : $payment;
    }

    /**
     * Apply an authoritative status. Idempotent: replays and late duplicates change nothing.
     */
    public function apply(Payment $payment, PaymentNotification $notification): Payment
    {
        if ($notification->externalId !== $payment->external_id) {
            Log::warning('Payment notification ignored: external id mismatch', ['payment' => $payment->ulid]);

            return $payment;
        }

        $result = DB::transaction(function () use ($payment, $notification): array {
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== PaymentStatus::Pending || $notification->status === PaymentStatus::Pending) {
                return [$payment, null];
            }

            if ($notification->status === PaymentStatus::Paid) {
                if ((int) $notification->paidAmount < $order->amount) {
                    Log::warning('Payment amount lower than order amount; not activating', [
                        'order' => $order->number, 'paid' => $notification->paidAmount, 'expected' => $order->amount,
                    ]);

                    return [$payment, null];
                }

                $payment->update([
                    'status' => PaymentStatus::Paid,
                    'paid_amount' => $notification->paidAmount,
                    'method' => $notification->method,
                    'paid_at' => $notification->paidAt ?? now(),
                    'payload' => $notification->payload ?: $payment->payload,
                ]);

                if ($order->status !== OrderStatus::Paid) {
                    $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();
                    $this->subscriptions->activateFromOrder($order);

                    return [$payment, 'paid'];
                }

                return [$payment, null];
            }

            $payment->update(['status' => $notification->status, 'payload' => $notification->payload ?: $payment->payload]);

            if ($order->status === OrderStatus::Pending) {
                $order->update(['status' => $notification->status === PaymentStatus::Expired ? OrderStatus::Expired : OrderStatus::Failed]);

                return [$payment, 'failed'];
            }

            return [$payment, null];
        });

        [$payment, $outcome] = $result;
        $order = $payment->order()->with('user', 'plan', 'subscription')->first();

        if ($outcome === 'paid') {
            PaymentCompleted::dispatch($order, $payment);
            $order->user->notify(new PaymentSucceeded($order));
            $this->auditLogger->record('payment.paid', $order->user, $order, ['amount' => $payment->paid_amount, 'method' => $payment->method]);
        } elseif ($outcome === 'failed') {
            $order->user->notify(new PaymentFailed($order));
            $this->auditLogger->record('payment.'.$payment->status->value, $order->user, $order);
        }

        Log::info('Payment status applied', ['order' => $order->number, 'payment' => $payment->ulid, 'status' => $payment->status->value]);

        return $payment;
    }

    /**
     * Close unpaid orders whose payment window has passed (scheduled payments:expire-stale).
     * Each one is first re-checked at the gateway, so a payment whose webhook got lost is
     * activated instead of expired. Manual transfers are left to the admin: there is no gateway
     * to ask, and a late bank transfer must still be confirmable.
     *
     * @return array{expired: int, paid: int}
     */
    public function expireStale(): array
    {
        $counts = ['expired' => 0, 'paid' => 0];

        Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('expires_at', '<=', now()->subMinutes(self::EXPIRY_GRACE_MINUTES))
            ->with('latestPayment')
            ->chunkById(100, function ($orders) use (&$counts): void {
                foreach ($orders as $order) {
                    $payment = $order->latestPayment;

                    if ($payment === null || $payment->status !== PaymentStatus::Pending || $payment->gateway === 'manual') {
                        continue;
                    }

                    try {
                        $notification = $this->gateways->gateway($payment->gateway)->fetchPayment($payment);
                    } catch (Throwable $exception) {
                        $notification = null;
                    }

                    if ($notification === null) {
                        // No answer from the gateway: try again on the next run rather than expire a payment we could not check.
                        Log::warning('Stale payment could not be checked at the gateway', ['payment' => $payment->ulid]);

                        continue;
                    }

                    // The gateway still says "waiting" after the window closed: nothing can be paid on it anymore.
                    $payment = $this->apply($payment, $notification->status === PaymentStatus::Pending
                        ? new PaymentNotification(externalId: $payment->external_id, status: PaymentStatus::Expired, reference: $payment->gateway_reference)
                        : $notification);

                    match ($payment->status) {
                        PaymentStatus::Paid => $counts['paid']++,
                        PaymentStatus::Expired, PaymentStatus::Failed => $counts['expired']++,
                        default => null,
                    };
                }
            });

        return $counts;
    }

    /**
     * Admin confirms a manual bank transfer.
     */
    public function confirmManually(Order $order, User $admin): Payment
    {
        $payment = $order->payments()->where('status', PaymentStatus::Pending)->latest()->firstOrFail();

        $this->auditLogger->record('payment.manually_confirmed', $admin, $order, ['amount' => $order->amount]);

        return $this->apply($payment, new PaymentNotification(
            externalId: $payment->external_id,
            status: PaymentStatus::Paid,
            reference: $payment->gateway_reference,
            paidAmount: $order->amount,
            method: 'manual transfer',
        ));
    }

    public function cancel(Order $order, User $user): void
    {
        if ($order->status !== OrderStatus::Pending) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $order->update(['status' => OrderStatus::Cancelled]);
            $order->payments()->where('status', PaymentStatus::Pending)->update(['status' => PaymentStatus::Failed]);
        });

        $this->auditLogger->record('order.cancelled', $user, $order);
    }

    private function orderNumber(): string
    {
        do {
            $number = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }

    /**
     * Payment channels to choose from, or null when the active gateway picks them on its own page.
     *
     * @return array<string, array<int, array{code: string, name: string, group: string, icon_url: ?string}>>|null
     *
     * @throws ValidationException
     */
    public function paymentMethods(): ?array
    {
        $gateway = $this->gateways->default();

        if (! $gateway instanceof RequiresPaymentMethod) {
            return null;
        }

        try {
            return collect($gateway->paymentMethods())->groupBy('group')->map->values()->map->all()->all();
        } catch (Throwable $exception) {
            Log::error('Payment methods unavailable', ['gateway' => $gateway->name(), 'error' => $exception->getMessage()]);

            throw ValidationException::withMessages(['price' => __('The payment page could not be opened. Please try again in a moment.')]);
        }
    }

    /**
     * Gateways fail in boring ways (timeouts, bad keys); report them as a friendly validation error.
     *
     * @throws ValidationException
     */
    public function checkoutOrFail(User $user, PlanPrice $price, ?string $method = null): Order
    {
        try {
            return $this->checkout($user, $price, $method);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Checkout failed', ['user' => $user->ulid, 'error' => $exception->getMessage()]);

            throw ValidationException::withMessages(['price' => __('The payment page could not be opened. Please try again in a moment.')]);
        }
    }
}
