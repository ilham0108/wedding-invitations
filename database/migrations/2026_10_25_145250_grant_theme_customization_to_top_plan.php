<?php

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;

/**
 * The editor's Theme panel is reserved for the highest plan (the owner's choice, 2026-10-25).
 * Every existing plan gets an explicit value: true only for the top-ranked paid plan(s).
 * Active subscriptions to those plans also get the feature, because subscription features
 * are frozen at purchase. Admins can change the value per plan in the plan manager later.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $topRank = Plan::query()->where('is_free', false)->max('rank');

        Plan::query()->each(function (Plan $plan) use ($topRank): void {
            $enabled = ! $plan->is_free && $topRank !== null && (int) $plan->rank === (int) $topRank;

            $plan->forceFill(['features' => [...(array) $plan->features, 'theme_customization' => $enabled]])->saveQuietly();

            if ($enabled) {
                $plan->subscriptions()->current()->each(function (Subscription $subscription): void {
                    $subscription->items()->updateOrCreate(['feature' => 'theme_customization'], ['value' => json_encode(true)]);
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
            $features = (array) $plan->features;
            unset($features['theme_customization']);

            $plan->forceFill(['features' => $features])->saveQuietly();
            $plan->subscriptions()->each(fn (Subscription $subscription) => $subscription->items()->where('feature', 'theme_customization')->delete());
        });
    }
};
