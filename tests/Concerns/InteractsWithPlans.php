<?php

namespace Tests\Concerns;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;

trait InteractsWithPlans
{
    /**
     * @param  array<string, int|bool|null>  $features
     */
    protected function freePlan(array $features = []): Plan
    {
        return Plan::factory()->free()->features($features)->create(['slug' => 'free', 'name' => 'Free']);
    }

    /**
     * Give the user an active paid plan with the given features (null limit = unlimited).
     *
     * @param  array<string, int|bool|null>  $features
     */
    protected function subscribe(User $user, array $features = [], int $rank = 20, int $days = 365): Subscription
    {
        $plan = Plan::factory()->features($features)->create(['rank' => $rank]);

        return app(SubscriptionService::class)->grant($user, $plan, $days);
    }

    protected function unlimited(User $user): Subscription
    {
        return $this->subscribe($user, [
            'max_invitations' => null, 'max_guests' => null, 'max_whatsapp_messages' => null,
            'premium_templates' => true, 'premium_illustrations' => true, 'remove_branding' => true,
        ]);
    }
}
