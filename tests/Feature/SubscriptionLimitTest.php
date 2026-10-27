<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

class SubscriptionLimitTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, RefreshDatabase;

    private function limits(): SubscriptionLimitService
    {
        // Fresh instance: the scoped singleton memoizes per request.
        app()->forgetScopedInstances();

        return app(SubscriptionLimitService::class);
    }

    public function test_free_plan_applies_without_a_subscription_and_a_subscription_overrides_it(): void
    {
        $this->freePlan(['max_invitations' => 1, 'max_guests' => 50, 'premium_templates' => false]);
        $user = User::factory()->client()->create();

        $this->assertSame(1, $this->limits()->limit($user, 'max_invitations'));
        $this->assertSame('Free', $this->limits()->plan($user)->name);
        $this->assertFalse($this->limits()->enabled($user, 'premium_templates'));

        $this->subscribe($user, ['max_invitations' => 3, 'max_guests' => null, 'premium_templates' => true]);

        $this->assertSame(3, $this->limits()->limit($user, 'max_invitations'));
        $this->assertNull($this->limits()->limit($user, 'max_guests'), 'null means unlimited');
        $this->assertTrue($this->limits()->enabled($user, 'premium_templates'));
    }

    public function test_purchased_features_are_frozen_when_the_plan_changes_later(): void
    {
        $user = User::factory()->client()->create();
        $subscription = $this->subscribe($user, ['max_guests' => 300]);

        $subscription->plan->update(['features' => ['max_guests' => 10]]);

        $this->assertSame(300, $this->limits()->limit($user, 'max_guests'));
    }

    public function test_an_ended_subscription_stops_counting_immediately(): void
    {
        $this->freePlan(['max_invitations' => 1]);
        $user = User::factory()->client()->create();
        $subscription = $this->subscribe($user, ['max_invitations' => 5]);

        $this->travelTo($subscription->ends_at->addMinute());

        $this->assertSame(1, $this->limits()->limit($user, 'max_invitations'));
    }

    public function test_super_admins_are_never_limited(): void
    {
        $this->freePlan(['max_invitations' => 0, 'premium_templates' => false]);
        $admin = User::factory()->superAdmin()->create();

        $this->assertNull($this->limits()->limit($admin, 'max_invitations'));
        $this->assertTrue($this->limits()->canUseTemplate($admin, InvitationTemplate::factory()->create(['is_premium' => true])));
    }

    public function test_invitations_beyond_the_plan_go_offline_oldest_first(): void
    {
        $this->freePlan(['max_invitations' => 1, 'premium_templates' => false]);
        $owner = User::factory()->client()->create();

        $oldest = $this->createInvitation($owner);
        $this->travel(1)->minutes();
        $newer = $this->createInvitation($owner);

        $this->assertTrue($this->limits()->isInvitationActive($oldest));
        $this->assertFalse($this->limits()->isInvitationActive($newer));

        $this->subscribe($owner, ['max_invitations' => 3]);

        $this->assertTrue($this->limits()->isInvitationActive($newer));
    }

    public function test_premium_template_invitations_need_a_plan_that_covers_them(): void
    {
        $this->freePlan(['max_invitations' => 5, 'premium_templates' => false]);
        $invitation = $this->createInvitation();
        $invitation->template->update(['is_premium' => true]);

        $this->assertFalse($this->limits()->isInvitationActive($invitation->fresh()));

        $this->subscribe($invitation->user, ['max_invitations' => 5, 'premium_templates' => true]);

        $this->assertTrue($this->limits()->isInvitationActive($invitation->fresh()));
    }
}
