<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Events\PaymentCompleted;
use App\Events\SubscriptionActivated;
use App\Livewire\OrderManager;
use App\Livewire\OrderStatus as OrderStatusComponent;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Notifications\PaymentSucceeded;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

/**
 * Checkout with the manual (bank transfer) gateway, the default until Xendit keys are set.
 */
class CheckoutTest extends TestCase
{
    use InteractsWithPlans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payment.default' => 'manual']);
        $this->freePlan(['max_invitations' => 1]);
    }

    private function price(array $planFeatures = ['max_invitations' => 3], int $rank = 20, array $attributes = []): PlanPrice
    {
        $plan = Plan::factory()->features($planFeatures)->create(['rank' => $rank, 'name' => 'Premium']);

        return PlanPrice::factory()->for($plan)->create($attributes);
    }

    private function checkout(User $user, PlanPrice $price): Order
    {
        $this->actingAs($user)->post('/dashboard/subscription/checkout', ['price' => $price->ulid])->assertRedirect();

        return $user->orders()->latest('id')->firstOrFail();
    }

    public function test_plans_page_lists_plans_and_usage(): void
    {
        $this->price();

        $this->actingAs(User::factory()->client()->create())
            ->get('/dashboard/subscription')
            ->assertOk()
            ->assertSee('Premium')
            ->assertSee('Rp 149.000')
            ->assertSee('Current plan');
    }

    public function test_manual_checkout_creates_a_pending_order_with_instructions(): void
    {
        $user = User::factory()->client()->create();
        $order = $this->checkout($user, $this->price());

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(149000, $order->amount);
        $this->assertSame('manual', $order->latestPayment()->first()->gateway);

        $this->actingAs($user)->get('/dashboard/subscription/orders/'.$order->ulid)
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('How to pay');

        // A second click reuses the open checkout instead of creating another invoice.
        $this->assertTrue($this->checkout($user, $order->price()->first())->is($order));
    }

    public function test_free_inactive_and_lower_plans_cannot_be_bought(): void
    {
        $user = User::factory()->client()->create();
        $this->subscribe($user, ['max_invitations' => 10], rank: 30);

        $lower = $this->price(rank: 10);
        $inactive = $this->price(attributes: ['is_active' => false]);

        $this->actingAs($user)->post('/dashboard/subscription/checkout', ['price' => $lower->ulid])->assertSessionHasErrors('price');
        $this->actingAs($user)->post('/dashboard/subscription/checkout', ['price' => $inactive->ulid])->assertSessionHasErrors('price');

        $this->assertSame(0, $user->orders()->count());
    }

    public function test_admin_confirmation_activates_the_plan_exactly_once(): void
    {
        Notification::fake();
        Event::fake([PaymentCompleted::class, SubscriptionActivated::class]);
        $user = User::factory()->client()->create();
        $order = $this->checkout($user, $this->price(['max_invitations' => 3, 'max_guests' => 900]));
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(OrderManager::class, ['tab' => 'orders'])
            ->assertSee($order->number)
            ->call('confirmPayment', $order->ulid)
            ->assertDispatched('notify', type: 'success', message: "Payment confirmed and plan activated for order {$order->number}.");

        $order->refresh();
        $subscription = $user->subscriptions()->sole();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->latestPayment()->first()->status);
        $this->assertTrue($order->subscription->is($subscription));
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->ends_at->between(now()->addDays(364), now()->addDays(366)));
        $features = $subscription->load('items')->features();
        $this->assertSame(3, $features['max_invitations']);
        $this->assertSame(900, $features['max_guests']);
        Notification::assertSentTo($user, PaymentSucceeded::class);
        Event::assertDispatchedTimes(PaymentCompleted::class, 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.manually_confirmed']);

        // Confirming again is impossible: the order is no longer pending.
        Livewire::actingAs($admin)->test(OrderManager::class, ['tab' => 'orders'])->call('confirmPayment', $order->ulid)->assertNotFound();
        $this->assertSame(1, $user->subscriptions()->count());
    }

    public function test_buying_the_same_plan_extends_and_a_higher_plan_replaces(): void
    {
        $user = User::factory()->client()->create();
        $admin = User::factory()->superAdmin()->create();
        $payments = app(PaymentService::class);
        $premium = $this->price(rank: 20);

        $payments->confirmManually($this->checkout($user, $premium), $admin);
        $firstEnd = $user->subscriptions()->sole()->ends_at;

        $this->travel(1)->hours();
        $payments->confirmManually($this->checkout($user, $premium), $admin);

        $this->assertSame(1, $user->subscriptions()->count());
        $this->assertTrue($user->subscriptions()->sole()->ends_at->equalTo($firstEnd->addDays(365)));

        $business = $this->price(rank: 30, attributes: ['billing_period' => 'monthly', 'duration_days' => null, 'amount' => 299000]);
        $payments->confirmManually($this->checkout($user, $business), $admin);

        $this->assertSame(SubscriptionStatus::Cancelled, $user->subscriptions()->oldest('id')->first()->status);
        $current = $user->subscriptions()->latest('id')->first();
        $this->assertSame($business->plan_id, $current->plan_id);
        $this->assertTrue($current->ends_at->between(now()->addDays(27), now()->addDays(32)));
    }

    public function test_orders_are_private_and_can_be_cancelled(): void
    {
        $user = User::factory()->client()->create();
        $order = $this->checkout($user, $this->price());

        $this->actingAs(User::factory()->client()->create())->get('/dashboard/subscription/orders/'.$order->ulid)->assertNotFound();

        Livewire::actingAs($user)->test(OrderStatusComponent::class, ['orderUlid' => $order->ulid])->call('cancel');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }
}
