<?php

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;

/**
 * New plan features "premium_music" and "custom_music" follow "premium_templates" on existing plans.
 * Active subscriptions get them too, because subscription features are frozen at purchase.
 * Admins can change both per plan in the plan manager later.
 */
return new class extends Migration
{
    private const FEATURES = ['premium_music', 'custom_music'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Plan::query()->each(function (Plan $plan): void {
            $enabled = (bool) ($plan->resolvedFeatures()['premium_templates'] ?? false);

            $plan->forceFill(['features' => [...(array) $plan->features, ...array_fill_keys(self::FEATURES, $enabled)]])->saveQuietly();

            if ($enabled) {
                $plan->subscriptions()->current()->each(function (Subscription $subscription): void {
                    foreach (self::FEATURES as $feature) {
                        $subscription->items()->updateOrCreate(['feature' => $feature], ['value' => json_encode(true)]);
                    }
                });
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Plan::query()->each(function (Plan $plan): void {
            $plan->forceFill(['features' => array_diff_key((array) $plan->features, array_flip(self::FEATURES))])->saveQuietly();
            $plan->subscriptions()->each(fn (Subscription $subscription) => $subscription->items()->whereIn('feature', self::FEATURES)->delete());
        });
    }
};
