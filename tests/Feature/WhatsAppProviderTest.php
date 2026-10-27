<?php

namespace Tests\Feature;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Enums\WhatsAppDriver;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\Providers\FonnteProvider;
use App\Services\WhatsApp\Providers\LogProvider;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Provider implementations against faked HTTP responses (no real API is called).
 */
class WhatsAppProviderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $credentials
     */
    private function provider(WhatsAppDriver $driver, array $credentials): WhatsAppProviderInterface
    {
        $config = WhatsAppProvider::factory()->create(['driver' => $driver, 'credentials' => $credentials]);

        return app(WhatsAppManager::class)->make($config);
    }

    public function test_manager_requires_an_active_provider(): void
    {
        WhatsAppProvider::factory()->inactive()->create();

        $this->expectException(WhatsAppNotConfiguredException::class);
        $this->expectExceptionMessage('WhatsApp provider is not configured.');

        app(WhatsAppManager::class)->active();
    }

    public function test_manager_resolves_the_active_driver_and_activation_is_exclusive(): void
    {
        $log = WhatsAppProvider::factory()->create();
        $fonnte = WhatsAppProvider::factory()->fonnte()->inactive()->create();
        $manager = app(WhatsAppManager::class);

        $this->assertInstanceOf(LogProvider::class, $manager->active());

        $manager->activate($fonnte);

        $this->assertInstanceOf(FonnteProvider::class, $manager->active());
        $this->assertFalse($log->fresh()->is_active);
    }

    public function test_fonnte_sends_with_token_header_and_reads_message_id(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['80367170'], 'process' => 'pending'])]);

        $result = $this->provider(WhatsAppDriver::Fonnte, ['token' => 'fonnte-token'])->sendMessage('6281234567890', 'Hello');

        $this->assertTrue($result->success);
        $this->assertSame('80367170', $result->messageId);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.fonnte.com/send'
            && $request->hasHeader('Authorization', 'fonnte-token')
            && $request['target'] === '6281234567890'
            && $request['message'] === 'Hello');
    }

    public function test_fonnte_rejection_is_not_retryable_but_rate_limit_and_timeouts_are(): void
    {
        $fonnte = $this->provider(WhatsAppDriver::Fonnte, ['token' => 't']);

        Http::fake(['*' => Http::sequence()
            ->push(['status' => false, 'reason' => 'invalid target'])
            ->push('Too many', 429)
            ->pushFailedConnection()]);

        $rejected = $fonnte->sendMessage('6281234567890', 'Hi');
        $this->assertFalse($rejected->success);
        $this->assertFalse($rejected->retryable);
        $this->assertSame('invalid target', $rejected->error);

        $this->assertTrue($fonnte->sendMessage('6281234567890', 'Hi')->retryable);

        $timeout = $fonnte->sendMessage('6281234567890', 'Hi');
        $this->assertTrue($timeout->retryable);
        $this->assertSame('Could not reach the WhatsApp provider.', $timeout->error);
    }

    public function test_fonnte_errors_get_actionable_explanations(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['status' => false, 'reason' => 'request invalid on disconnected device'])
            ->push(['status' => false, 'reason' => 'invalid token'])
            ->push(['status' => false])]);

        $fonnte = $this->provider(WhatsAppDriver::Fonnte, ['token' => 't']);

        $this->assertSame(
            'Your WhatsApp device is not connected in Fonnte. Open the Fonnte dashboard → Device → Connect, then scan the QR code from WhatsApp → Linked devices. (request invalid on disconnected device)',
            $fonnte->sendMessage('6281234567890', 'Hi')->error,
        );
        $this->assertStringStartsWith('The Fonnte token is not valid.', (string) $fonnte->sendMessage('6281234567890', 'Hi')->error);
        $this->assertSame('Fonnte rejected the message.', $fonnte->sendMessage('6281234567890', 'Hi')->error);
    }

    public function test_wablas_signs_with_token_and_secret(): void
    {
        Http::fake(['jkt.wablas.com/*' => Http::response(['status' => true, 'data' => ['messages' => [['id' => 'wb-1']]]])]);

        $result = $this->provider(WhatsAppDriver::Wablas, ['base_url' => 'https://jkt.wablas.com/', 'token' => 'tok', 'secret_key' => 'sec'])
            ->sendMessage('6281234567890', 'Hello');

        $this->assertSame('wb-1', $result->messageId);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://jkt.wablas.com/api/send-message'
            && $request->hasHeader('Authorization', 'tok.sec')
            && $request['phone'] === '6281234567890');
    }

    public function test_meta_sends_a_text_message_and_classifies_rate_limit_errors(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['messages' => [['id' => 'wamid.ABC']]])
            ->push(['error' => ['message' => 'Rate limit hit', 'code' => 130429]], 400)
            ->push(['error' => ['message' => 'Invalid parameter', 'code' => 100]], 400)]);

        $meta = $this->provider(WhatsAppDriver::Meta, ['phone_number_id' => '1234', 'access_token' => 'meta-token']);

        $this->assertSame('wamid.ABC', $meta->sendMessage('6281234567890', 'Hello')->messageId);
        $this->assertTrue($meta->sendMessage('6281234567890', 'Hello')->retryable);
        $this->assertFalse($meta->sendMessage('6281234567890', 'Hello')->retryable);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v21.0/1234/messages'
            && $request->hasHeader('Authorization', 'Bearer meta-token')
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '6281234567890'
            && $request['text']['body'] === 'Hello');
    }

    public function test_twilio_uses_whatsapp_prefixed_numbers_and_basic_auth(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201)]);

        $result = $this->provider(WhatsAppDriver::Twilio, ['account_sid' => 'AC1', 'auth_token' => 'secret', 'from' => '+14155238886'])
            ->sendMessage('6281234567890', 'Hello');

        $this->assertSame('SM123', $result->messageId);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC1/Messages.json'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('AC1:secret'))
            && $request['From'] === 'whatsapp:+14155238886'
            && $request['To'] === 'whatsapp:+6281234567890');
    }

    public function test_bulk_send_returns_one_result_per_message(): void
    {
        Http::fake(['*' => Http::sequence()->push(['status' => true, 'id' => ['1']])->push(['status' => false, 'reason' => 'bad'])]);

        $results = $this->provider(WhatsAppDriver::Fonnte, ['token' => 't'])->sendBulk([
            'a' => ['phone' => '6281111111111', 'message' => 'A'],
            'b' => ['phone' => '6282222222222', 'message' => 'B'],
        ]);

        $this->assertTrue($results['a']->success);
        $this->assertFalse($results['b']->success);
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $provider = WhatsAppProvider::factory()->fonnte()->create();

        $raw = WhatsAppProvider::query()->whereKey($provider->id)->toBase()->value('credentials');

        $this->assertStringNotContainsString('fonnte-secret-token', (string) $raw);
        $this->assertSame('fonnte-secret-token', $provider->fresh()->credential('token'));
        $this->assertArrayNotHasKey('credentials', $provider->toArray());
    }
}
