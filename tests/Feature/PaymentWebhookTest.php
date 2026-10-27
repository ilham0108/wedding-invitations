<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\ProcessPaymentWebhook;
use App\Livewire\OrderStatus as OrderStatusComponent;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Notifications\PaymentFailed;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

/**
 * Xendit Invoice flow against faked HTTP. A webhook is only a hint; activation needs the
 * gateway's own answer to GET /v2/invoices/{id}.
 */
class PaymentWebhookTest extends TestCase
{
    use InteractsWithPlans, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.default' => 'xendit',
            'payment.gateways.xendit.secret_key' => 'xnd_development_secret',
            'payment.gateways.xendit.callback_token' => 'callback-token-123',
        ]);

        $this->user = User::factory()->client()->create(['email' => 'buyer@example.com']);
    }

    private function checkoutWithXendit(): Order
    {
        $price = PlanPrice::factory()->for(Plan::factory()->features(['max_invitations' => 3])->create(['name' => 'Premium']))->create();

        Http::fake(['api.xendit.co/v2/invoices' => Http::response([
            'id' => 'inv_123', 'status' => 'PENDING', 'invoice_url' => 'https://checkout.xendit.co/web/inv_123', 'expiry_date' => now()->addDay()->toIso8601String(),
        ])]);

        $this->actingAs($this->user)
            ->post('/dashboard/subscription/checkout', ['price' => $price->ulid])
            ->assertRedirect('https://checkout.xendit.co/web/inv_123');

        return $this->user->orders()->sole();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function webhook(array $body, string $token = 'callback-token-123'): TestResponse
    {
        return $this->postJson('/webhooks/payments/xendit', $body, ['x-callback-token' => $token]);
    }

    public function test_checkout_creates_a_xendit_invoice(): void
    {
        $order = $this->checkoutWithXendit();
        $payment = $order->latestPayment()->first();

        $this->assertSame('inv_123', $payment->gateway_reference);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.xendit.co/v2/invoices'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('xnd_development_secret:'))
            && $request['external_id'] === $payment->external_id
            && $request['amount'] === 149000
            && $request['currency'] === 'IDR'
            && $request['payer_email'] === 'buyer@example.com');
    }

    public function test_gateway_errors_become_a_friendly_message(): void
    {
        $price = PlanPrice::factory()->for(Plan::factory()->create())->create();
        Http::fake(['api.xendit.co/*' => Http::response(['message' => 'API key invalid'], 401)]);

        $this->actingAs($this->user)
            ->post('/dashboard/subscription/checkout', ['price' => $price->ulid])
            ->assertSessionHasErrors(['price' => 'The payment page could not be opened. Please try again in a moment.']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_webhook_with_a_wrong_token_is_rejected(): void
    {
        Queue::fake();
        $order = $this->checkoutWithXendit();

        $this->webhook(['external_id' => $order->latestPayment()->first()->external_id, 'status' => 'PAID'], 'forged')->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_verified_webhook_activates_only_after_the_api_confirms(): void
    {
        $order = $this->checkoutWithXendit();
        $payment = $order->latestPayment()->first();

        Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response([
            'id' => 'inv_123', 'external_id' => $payment->external_id, 'status' => 'PAID',
            'paid_amount' => 149000, 'payment_method' => 'BANK_TRANSFER', 'payment_channel' => 'BCA', 'paid_at' => now()->toIso8601String(),
        ])]);

        $this->webhook(['id' => 'inv_123', 'external_id' => $payment->external_id, 'status' => 'PAID', 'paid_amount' => 149000])->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('BANK_TRANSFER BCA', $payment->fresh()->method);
        $this->assertNotNull($this->user->subscriptions()->current()->first());

        // Replaying the same webhook changes nothing.
        $this->webhook(['id' => 'inv_123', 'external_id' => $payment->external_id, 'status' => 'PAID'])->assertOk();
        $this->assertSame(1, $this->user->subscriptions()->count());
    }

    public function test_a_forged_paid_body_is_not_trusted_when_the_api_says_pending(): void
    {
        Queue::fake();
        $order = $this->checkoutWithXendit();
        $payment = $order->latestPayment()->first();

        $this->webhook(['id' => 'inv_123', 'external_id' => $payment->external_id, 'status' => 'PAID', 'paid_amount' => 149000])->assertOk();
        Queue::assertPushed(ProcessPaymentWebhook::class);

        Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response(['id' => 'inv_123', 'external_id' => $payment->external_id, 'status' => 'PENDING'])]);

        try {
            (new ProcessPaymentWebhook($payment->ulid))->handle(app(PaymentService::class));
            $this->fail('Job should retry while the gateway reports pending.');
        } catch (RuntimeException) {
            // Retried later by the queue.
        }

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(0, $this->user->subscriptions()->count());
    }

    public function test_underpaid_invoices_do_not_activate(): void
    {
        $order = $this->checkoutWithXendit();
        $payment = $order->latestPayment()->first();

        Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response(['id' => 'inv_123', 'external_id' => $payment->external_id, 'status' => 'PAID', 'paid_amount' => 1000])]);

        app(PaymentService::class)->sync($payment);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(0, $this->user->subscriptions()->count());
    }

    public function test_expired_invoice_marks_the_order_and_notifies_the_customer(): void
    {
        Notification::fake();
        $order = $this->checkoutWithXendit();
        $payment = $order->latestPayment()->first();

        Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response(['id' => 'inv_123', 'external_id' => $payment->external_id, 'status' => 'EXPIRED'])]);

        $this->webhook(['id' => 'inv_123', 'external_id' => $payment->external_id, 'status' => 'EXPIRED'])->assertOk();

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        Notification::assertSentTo($this->user, PaymentFailed::class);
    }

    public function test_unknown_payments_are_acknowledged_and_ignored(): void
    {
        Queue::fake();

        $this->webhook(['id' => 'inv_x', 'external_id' => 'INV-NOT-OURS', 'status' => 'PAID'])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_return_page_asks_the_gateway_instead_of_trusting_the_redirect(): void
    {
        $order = $this->checkoutWithXendit();
        $payment = $order->latestPayment()->first();

        Http::fake(['api.xendit.co/v2/invoices/inv_123' => Http::response(['id' => 'inv_123', 'external_id' => $payment->external_id, 'status' => 'PAID', 'paid_amount' => 149000])]);

        Livewire::actingAs($this->user)
            ->test(OrderStatusComponent::class, ['orderUlid' => $order->ulid])
            ->call('refreshStatus')
            ->assertSee('Payment received.');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
