<?php

namespace App\Services\Subscription;

use App\Enums\BillingPeriod;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Admin plan management. Prices that orders already used are deactivated, never deleted,
 * and existing subscribers keep the feature values they bought.
 */
class PlanService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{slug: string, name: string, description: ?string, is_active: bool, is_highlighted: bool, is_free: bool, rank: int, sort_order: int}  $attributes
     * @param  array<string, int|bool|null>  $features
     * @param  array<int, array{ulid?: ?string, billing_period: string, duration_days: ?int, amount: int, is_active: bool}>  $prices
     */
    public function save(Plan $plan, array $attributes, array $features, array $prices, User $admin): Plan
    {
        $wasNew = ! $plan->exists;

        DB::transaction(function () use ($plan, $attributes, $features, $prices): void {
            $plan->fill([...$attributes, 'features' => $this->cleanFeatures($features)])->save();

            if ($plan->is_free) {
                Plan::query()->whereKeyNot($plan->getKey())->where('is_free', true)->update(['is_free' => false]);
            }

            $keep = [];

            foreach ($prices as $row) {
                $period = BillingPeriod::from($row['billing_period']);
                $price = ! empty($row['ulid'])
                    ? $plan->prices()->where('ulid', $row['ulid'])->firstOrFail()
                    : $plan->prices()->make();

                $price->fill([
                    'billing_period' => $period,
                    'duration_days' => $period === BillingPeriod::OneTime ? max(1, (int) $row['duration_days']) : null,
                    'amount' => max(0, (int) $row['amount']),
                    'currency' => 'IDR',
                    'is_active' => (bool) $row['is_active'],
                ])->save();

                $keep[] = $price->getKey();
            }

            // Removed rows: delete when never ordered, otherwise just stop selling them.
            $plan->prices()->whereKeyNot($keep)->get()->each(function ($price): void {
                Order::query()->where('plan_price_id', $price->getKey())->exists()
                    ? $price->update(['is_active' => false])
                    : $price->delete();
            });
        });

        $this->auditLogger->record($wasNew ? 'plan.created' : 'plan.updated', $admin, $plan, ['slug' => $plan->slug]);

        return $plan;
    }

    /**
     * @return bool true when deleted, false when it had customers and was deactivated instead
     */
    public function remove(Plan $plan, User $admin): bool
    {
        $inUse = $plan->is_free
            || Subscription::query()->where('plan_id', $plan->getKey())->exists()
            || Order::query()->where('plan_id', $plan->getKey())->exists();

        $inUse ? $plan->update(['is_active' => false]) : $plan->delete();

        $this->auditLogger->record($inUse ? 'plan.deactivated' : 'plan.deleted', $admin, $plan, ['slug' => $plan->slug]);

        return ! $inUse;
    }

    /**
     * Keep only catalog features, cast to their declared type.
     *
     * @param  array<string, mixed>  $features
     * @return array<string, int|bool|null>
     */
    private function cleanFeatures(array $features): array
    {
        $clean = [];

        foreach (config('plans.features') as $key => $definition) {
            // array_key_exists, not ??: null is a real value here (unlimited).
            $value = array_key_exists($key, $features) ? $features[$key] : $definition['default'];
            $clean[$key] = $definition['type'] === 'boolean'
                ? (bool) $value
                : ($value === null || $value === '' ? null : max(0, (int) $value));
        }

        return $clean;
    }
}
