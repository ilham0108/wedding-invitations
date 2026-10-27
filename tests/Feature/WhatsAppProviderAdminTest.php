<?php

namespace Tests\Feature;

use App\Enums\WhatsAppDriver;
use App\Livewire\WhatsAppProviderManager;
use App\Models\User;
use App\Models\WhatsAppProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppProviderAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_provider_settings(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/whatsapp')->assertOk()->assertSeeLivewire(WhatsAppProviderManager::class);
        $this->actingAs(User::factory()->client()->create())->get('/admin/whatsapp')->assertForbidden();

        Livewire::actingAs(User::factory()->client()->create())->test(WhatsAppProviderManager::class)->assertForbidden();
    }

    public function test_admin_adds_a_provider_and_the_first_one_becomes_active(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(WhatsAppProviderManager::class)
            ->call('create')
            ->set('driver', 'fonnte')
            ->set('name', 'Fonnte main')
            ->set('ratePerMinute', 30)
            ->set('credentials.token', 'super-secret-token')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Fonnte main')
            ->assertDontSee('super-secret-token');

        $provider = WhatsAppProvider::query()->sole();
        $this->assertSame(WhatsAppDriver::Fonnte, $provider->driver);
        $this->assertTrue($provider->is_active);
        $this->assertSame('super-secret-token', $provider->credential('token'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'whatsapp.provider_created']);
    }

    public function test_required_credentials_are_validated(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(WhatsAppProviderManager::class)
            ->call('create')
            ->set('driver', 'wablas')
            ->set('name', 'Wablas')
            ->set('credentials.base_url', 'http://insecure.test')
            ->call('save')
            ->assertHasErrors(['credentials.token', 'credentials.base_url']);

        $this->assertSame(0, WhatsAppProvider::query()->count());
    }

    public function test_editing_never_reveals_secrets_and_blank_secret_keeps_the_stored_value(): void
    {
        $provider = WhatsAppProvider::factory()->create([
            'driver' => WhatsAppDriver::Meta,
            'name' => 'Meta',
            'credentials' => ['phone_number_id' => '123', 'access_token' => 'meta-secret', 'app_secret' => 'app-secret'],
        ]);

        $component = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(WhatsAppProviderManager::class)
            ->call('edit', $provider->ulid)
            ->assertSet('credentials.access_token', '')
            ->assertSet('credentials.phone_number_id', '123')
            ->assertSee('Saved. Leave blank to keep the current value.')
            ->assertSee(route('webhooks.whatsapp', ['driver' => 'meta']));

        $this->assertStringNotContainsString('meta-secret', json_encode($component->snapshot));
        $this->assertStringNotContainsString('app-secret', $component->html());

        $component->set('credentials.phone_number_id', '456')->call('save')->assertHasNoErrors();

        $provider->refresh();
        $this->assertSame('456', $provider->credential('phone_number_id'));
        $this->assertSame('meta-secret', $provider->credential('access_token'));
    }

    public function test_activating_a_provider_deactivates_the_others(): void
    {
        $first = WhatsAppProvider::factory()->create();
        $second = WhatsAppProvider::factory()->fonnte()->inactive()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(WhatsAppProviderManager::class)
            ->call('activate', $second->ulid);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_admin_can_send_a_test_message(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['1']])]);
        $provider = WhatsAppProvider::factory()->fonnte()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(WhatsAppProviderManager::class)
            ->set('testPhone', '0812-3456-7890')
            ->call('sendTest', $provider->ulid)
            ->assertDispatched('notify', type: 'success', message: 'Test message sent.');

        Http::assertSent(fn (Request $request): bool => $request['target'] === '6281234567890');
        $this->assertNotNull($provider->fresh()->last_tested_at);
    }
}
