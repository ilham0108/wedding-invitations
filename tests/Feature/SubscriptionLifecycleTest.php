<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, RefreshDatabase;

    public function test_owners_are_reminded_once_then_the_plan_expires(): void
    {
        Notification::fake();
        $user = User::factory()->client()->create();
        $subscription = $this->subscribe($user, ['max_invitations' => 3], days: 10);

        $this->artisan('subscriptions:process-expirations')->expectsOutputToContain('Reminded 0, expired 0');

        $this->travel(4)->days();
        $this->artisan('subscriptions:process-expirations')->expectsOutputToContain('Reminded 1, expired 0');
        $this->artisan('subscriptions:process-expirations')->expectsOutputToContain('Reminded 0, expired 0');
        Notification::assertSentToTimes($user, SubscriptionExpiring::class, 1);

        $this->travel(7)->days();
        $this->artisan('subscriptions:process-expirations')->expectsOutputToContain('expired 1');

        $this->assertSame(SubscriptionStatus::Expired, $subscription->fresh()->status);
        Notification::assertSentTo($user, SubscriptionExpired::class);
    }

    public function test_extra_invitations_go_offline_when_the_plan_ends_and_return_on_renewal(): void
    {
        $this->freePlan(['max_invitations' => 1]);
        $owner = User::factory()->client()->create();
        $this->subscribe($owner, ['max_invitations' => 3], days: 30);

        $first = $this->createInvitation($owner);
        $this->travel(1)->minutes();
        $second = $this->createInvitation($owner);

        foreach ([$first, $second] as $invitation) {
            $invitation->forceFill(['status' => InvitationStatus::Published, 'published_at' => now()])->save();
        }

        $this->get('/invitation/'.$second->slug)->assertOk();

        $this->travel(31)->days();
        app()->forgetScopedInstances();

        $this->get('/invitation/'.$first->slug)->assertOk();
        $this->get('/invitation/'.$second->slug)->assertForbidden();

        $this->subscribe($owner, ['max_invitations' => 3]);

        $this->get('/invitation/'.$second->slug)->assertOk();
    }
}
