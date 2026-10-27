<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Tripay closed payment against faked HTTP (no real Tripay account is called).
 */
class TripayGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PlanPrice $price;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.default' => 'tripay',
            'payment.gateways.tripay' => ['api_key' => 'DEV-api-key', 'private_key' => 'private-key-123', 'merchant_code' => 'T1234', 'sandbox' => true],
        ]);

        $this->user = User::factory()->client()->create(['email' => 'buyer@example.com', 'phone' => '6281234567890']);
        $this->price = PlanPrice::factory()->for(Plan::factory()->create(['name' => 'Premium']))->create(['amount' => 149000]);
    }

    private function fakeTripay(string $detailStatus = 'PAID', int $detailAmount = 149000): void
    {
        Http::fake(function (Request $request) use ($detailStatus, $detailAmount) {
            return match (true) {
                str_ends_with($request->url(), '/merchant/payment-channel') => Http::response(['success' => true, 'data' => [
                    ['group' => 'Virtual Account', 'code' => 'BRIVA', 'name' => 'BRI Virtual Account', 'active' => true, 'icon_url' => 'https://tripay.co.id/images/payment-channel/briva.png'],
                    ['group' => 'E-Wallet', 'code' => 'QRIS', 'name' => 'QRIS', 'active' => true, 'icon_url' => null],
                    ['group' => 'E-Wallet', 'code' => 'OVO', 'name' => 'OVO', 'active' => false],
                ]]),
                str_ends_with($request->url(), '/transaction/create') => Http::response(['success' => true, 'data' => [
                    'reference' => 'DEV-T1234-0001', 'merchant_ref' => $request['merchant_ref'], 'status' => 'UNPAID',
                    'checkout_url' => 'https://tripay.co.id/checkout/DEV-T1234-0001', 'amount' => 149000, 'expired_time' => now()->addDay()->timestamp,
                ]]),
                str_contains($request->url(), '/transaction/detail') => Http::response(['success' => true, 'data' => [
                    'reference' => 'DEV-T1234-0001', 'merchant_ref' => Order::query()->first()?->latestPayment()->value('external_id'),
                    'status' => $detailStatus, 'amount' => $detailAmount, 'payment_name' => 'QRIS', 'paid_at' => now()->timestamp,
                ]]),
                default => Http::response([], 404),
            };
        });
    }

    private function checkout(?string $method = 'QRIS'): TestResponse
    {
        return $this->actingAs($this->user)->post('/dashboard/subscription/checkout', array_filter(['price' => $this->price->ulid, 'method' => $method]));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function sendCallback(array $body, ?string $signature = null, string $event = 'payment_status'): TestResponse
    {
        $raw = (string) json_encode($body);

        return $this->call('POST', '/webhooks/payments/tripay', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_EVENT' => $event,
            'HTTP_X_CALLBACK_SIGNATURE' => $signature ?? hash_hmac('sha256', $raw, 'private-key-123'),
        ], content: $raw);
    }

    public function test_customer_picks_an_active_channel_before_paying(): void
    {
        $this->fakeTripay();

        $this->checkout(null)->assertRedirect(route('dashboard.subscription.method', $this->price->ulid));

        $this->actingAs($this->user)->get(route('dashboard.subscription.method', $this->price->ulid))
            ->assertOk()
            ->assertSee('Virtual Account')
            ->assertSee('BRI Virtual Account')
            ->assertSee('QRIS')
            ->assertDontSee('OVO');

        $this->checkout('OVO')->assertSessionHasErrors('method');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_creates_a_signed_tripay_transaction(): void
    {
        $this->fakeTripay();

        $this->checkout('QRIS')->assertRedirect('https://tripay.co.id/checkout/DEV-T1234-0001');

        $payment = $this->user->orders()->sole()->latestPayment()->first();
        $this->assertSame('tripay', $payment->gateway);
        $this->assertSame('QRIS', $payment->method);
        $this->assertSame('DEV-T1234-0001', $payment->gateway_reference);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://tripay.co.id/api-sandbox/transaction/create'
            && $request->hasHeader('Authorization', 'Bearer DEV-api-key')
            && $request['method'] === 'QRIS'
            && $request['merchant_ref'] === $payment->external_id
            && $request['amount'] === 149000
            && $request['customer_email'] === 'buyer@example.com'
            && $request['signature'] === hash_hmac('sha256', 'T1234'.$payment->external_id.'149000', 'private-key-123'));
    }

    public function test_production_mode_uses_the_live_api(): void
    {
        config(['payment.gateways.tripay.sandbox' => false]);
        $this->fakeTripay();

        $this->checkout('QRIS');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://tripay.co.id/api/transaction/create');
    }

    public function test_signed_callback_activates_after_the_api_confirms(): void
    {
        $this->fakeTripay('PAID');
        $this->checkout('QRIS');
        $payment = $this->user->orders()->sole()->latestPayment()->first();

        $this->sendCallback(['reference' => 'DEV-T1234-0001', 'merchant_ref' => $payment->external_id, 'status' => 'PAID'])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertSame(OrderStatus::Paid, $this->user->orders()->sole()->status);
        $this->assertSame('QRIS', $payment->fresh()->method);
        $this->assertNotNull($this->user->subscriptions()->current()->first());
    }

    public function test_bad_signatures_and_other_events_are_rejected(): void
    {
        Queue::fake();
        $this->fakeTripay();
        $this->checkout('QRIS');
        $body = ['reference' => 'DEV-T1234-0001', 'merchant_ref' => $this->user->orders()->sole()->latestPayment()->value('external_id'), 'status' => 'PAID'];

        $this->sendCallback($body, signature: 'forged')->assertForbidden();
        $this->sendCallback($body, event: 'something_else')->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_a_paid_callback_is_not_trusted_when_the_api_says_unpaid(): void
    {
        Queue::fake();
        $this->fakeTripay('UNPAID');
        $this->checkout('QRIS');
        $payment = $this->user->orders()->sole()->latestPayment()->first();

        $this->sendCallback(['reference' => 'DEV-T1234-0001', 'merchant_ref' => $payment->external_id, 'status' => 'PAID'])->assertOk();

        $this->expectException(RuntimeException::class);

        try {
            (new ProcessPaymentWebhook($payment->ulid))->handle(app(PaymentService::class));
        } finally {
            $this->assertSame(OrderStatus::Pending, $this->user->orders()->sole()->status);
            $this->assertSame(0, $this->user->subscriptions()->count());
        }
    }

    public function test_expired_transactions_close_the_order(): void
    {
        $this->fakeTripay('EXPIRED');
        $this->checkout('QRIS');
        $payment = $this->user->orders()->sole()->latestPayment()->first();

        app(PaymentService::class)->sync($payment);

        $this->assertSame(OrderStatus::Expired, $this->user->orders()->sole()->status);
    }
}
