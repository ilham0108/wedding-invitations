<?php

namespace App\Services\Subscription;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionActivated;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionExpiring;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns paid orders into subscriptions and retires subscriptions that ran out.
 */
class SubscriptionService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Same plan again extends the current period; a higher plan replaces it immediately.
     * Must run inside the caller's transaction (PaymentService locks the order).
     */
    public function activateFromOrder(Order $order): Subscription
    {
        $order->loadMissing('plan', 'user');

        $current = $order->user->subscriptions()->current()->orderByDesc('ends_at')->first();
        $now = CarbonImmutable::now();

        $extended = $current !== null && $current->plan_id === $order->plan_id;

        if ($extended) {
            $from = $current->ends_at !== null && $current->ends_at->isFuture() ? CarbonImmutable::instance($current->ends_at) : $now;
            $current->update([
                'ends_at' => $order->billing_period->extend($from, $order->duration_days),
                'billing_period' => $order->billing_period,
                'reminded_at' => null,
            ]);
            $subscription = $current;
        } else {
            $current?->update(['status' => SubscriptionStatus::Cancelled, 'ends_at' => $now]);
            $subscription = $this->start($order->user, $order->plan, $order->billing_period, $now, $order->billing_period->extend($now, $order->duration_days));
        }

        $order->subscription()->associate($subscription);
        $order->save();

        $this->auditLogger->record($extended ? 'subscription.extended' : 'subscription.activated', $order->user, $subscription, [
            'plan' => $order->plan->name,
            'order' => $order->number,
            'ends_at' => $subscription->ends_at?->toIso8601String(),
        ]);

        app(SubscriptionLimitService::class)->forget($order->user);
        SubscriptionActivated::dispatch($subscription, $order);

        return $subscription;
    }

    /**
     * Grant a plan without payment (seeding, support gestures). Feature values are frozen like a purchase.
     */
    public function grant(User $user, Plan $plan, int $days): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $days): Subscription {
            $user->subscriptions()->current()->update(['status' => SubscriptionStatus::Cancelled, 'ends_at' => now()]);

            $subscription = $this->start($user, $plan, BillingPeriod::OneTime, CarbonImmutable::now(), CarbonImmutable::now()->addDays($days));
            app(SubscriptionLimitService::class)->forget($user);

            $this->auditLogger->record('subscription.granted', $user, $subscription, ['plan' => $plan->name, 'days' => $days]);

            return $subscription;
        });
    }

    /**
     * Daily maintenance: remind owners before a paid plan ends, then expire it.
     *
     * @return array{reminded: int, expired: int}
     */
    public function processExpirations(): array
    {
        $reminded = 0;
        $expired = 0;

        Subscription::query()
            ->current()
            ->whereNull('reminded_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->addDays((int) config('plans.expiry_reminder_days')))
            ->with('user', 'plan')
            ->each(function (Subscription $subscription) use (&$reminded): void {
                $subscription->forceFill(['reminded_at' => now()])->save();
                $subscription->user->notify(new SubscriptionExpiring($subscription));
                $reminded++;
            });

        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->with('user', 'plan')
            ->each(function (Subscription $subscription) use (&$expired): void {
                $subscription->update(['status' => SubscriptionStatus::Expired]);
                $subscription->user->notify(new SubscriptionExpired($subscription));
                $this->auditLogger->record('subscription.expired', $subscription->user, $subscription, ['plan' => $subscription->plan->name]);
                $expired++;
            });

        return ['reminded' => $reminded, 'expired' => $expired];
    }

    private function start(User $user, Plan $plan, BillingPeriod $period, CarbonImmutable $startsAt, ?CarbonImmutable $endsAt): Subscription
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::Active,
            'billing_period' => $period,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $subscription->user()->associate($user);
        $subscription->plan()->associate($plan);
        $subscription->save();

        $subscription->items()->createMany(collect($plan->resolvedFeatures())
            ->map(fn (int|bool|null $value, string $feature): array => ['feature' => $feature, 'value' => json_encode($value)])
            ->values()
            ->all());

        return $subscription;
    }
}
