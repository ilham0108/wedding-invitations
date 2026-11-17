<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Notifications\PaymentFailed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

/**
 * payments:expire-stale closes unpaid orders past their window, after asking the gateway.
 */
class ExpireStalePaymentsTest extends TestCase
{
    use InteractsWithPlans, RefreshDatabase;

    private User $user;

    private PlanPrice $price;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.default' => 'xendit',
            'payment.gateways.xendit.secret_key' => 'xnd_development_secret',
            'payment.gateways.xendit.callback_token' => 'callback-token-123',
        ]);

        $this->user = User::factory()->client()->create();
        $this->price = PlanPrice::factory()->for(Plan::factory()->features(['max_invitations' => 3])->create(['name' => 'Premium']))->create(['amount' => 149000]);
    }

    private function checkout(string $gateway = 'xendit'): Order
    {
        config(['payment.default' => $gateway]);

        Http::fake(['api.xendit.co/v2/invoices' => Http::response([
            'id' => 'inv_123', 'status' => 'PENDING', 'invoice_url' => 'https://checkout.xendit.co/web/inv_123', 'expiry_date' => now()->addDay()->toIso8601String(),
        ])]);

        $this->actingAs($this->user)->post('/dashboard/subscription/checkout', ['price' => $this->price->ulid])->assertRedirect();

        return $this->user->orders()->latest('id')->firstOrFail();
    }

    private function gatewaySays(Order $order, string $status): void
    {
        Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response([
            'id' => 'inv_123', 'external_id' => $order->latestPayment()->value('external_id'), 'status' => $status,
            'paid_amount' => 149000, 'payment_method' => 'BANK_TRANSFER', 'payment_channel' => 'BCA', 'paid_at' => now()->toIso8601String(),
        ])]);
    }

    public function test_an_unpaid_order_past_its_window_is_expired_and_the_customer_told(): void
    {
        Notification::fake();
        $order = $this->checkout();
        $this->gatewaySays($order, 'PENDING');
        $this->travel(26)->hours();

        $this->artisan('payments:expire-stale')->expectsOutputToContain('Expired 1, activated 0')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Expired, $order->latestPayment()->first()->status);
        Notification::assertSentTo($this->user, PaymentFailed::class);
    }

    public function test_a_payment_whose_webhook_was_lost_is_activated_instead(): void
    {
        $order = $this->checkout();
        $this->gatewaySays($order, 'PAID');
        $this->travel(26)->hours();

        $this->artisan('payments:expire-stale')->expectsOutputToContain('Expired 0, activated 1')->assertSuccessful();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNotNull($this->user->subscriptions()->current()->first());
    }

    public function test_orders_inside_the_window_or_grace_period_are_left_alone(): void
    {
        $order = $this->checkout();
        $this->gatewaySays($order, 'PENDING');
        $this->travel(24 * 60 + 30)->minutes();

        $this->artisan('payments:expire-stale')->expectsOutputToContain('Expired 0, activated 0')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_manual_transfers_and_unreachable_gateways_are_not_expired(): void
    {
        $manual = $this->checkout('manual');
        $xendit = $this->checkout();
        Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response(['message' => 'Server error'], 500)]);
        $this->travel(30)->days();

        $this->artisan('payments:expire-stale')->expectsOutputToContain('Expired 0, activated 0')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $manual->fresh()->status, 'The admin can still confirm a late bank transfer.');
        $this->assertSame(OrderStatus::Pending, $xendit->fresh()->status, 'Checked again on the next run.');
    }

    public function test_the_command_runs_hourly(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => Str::contains((string) $event->command, 'payments:expire-stale'));

        $this->assertSame('0 * * * *', $event?->expression);
    }
}
