<?php

namespace Tests\Feature;

use App\Livewire\SystemSettingsForm;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_only_admins_open_the_settings(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.index'))->assertOk()->assertSeeLivewire(SystemSettingsForm::class);
        $this->actingAs(User::factory()->client()->create())->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_settings_are_validated_saved_and_audited(): void
    {
        Livewire::actingAs($this->admin())->test(SystemSettingsForm::class)
            ->set('settings.support_email', 'not-an-email')
            ->set('settings.default_timezone', 'Mars/Olympus')
            ->call('save')
            ->assertHasErrors(['settings.support_email', 'settings.default_timezone'])
            ->set('settings.support_email', 'help@invitefully.test')
            ->set('settings.default_timezone', 'Asia/Makassar')
            ->set('settings.site_name', 'Undangku')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $settings = app(SystemSettings::class);
        $this->assertSame('help@invitefully.test', $settings->get('support_email'));
        $this->assertSame('Asia/Makassar', $settings->get('default_timezone'));
        $this->assertSame('Undangku', $settings->get('site_name'));

        $log = AuditLog::query()->where('action', 'settings.updated')->sole();
        $this->assertSame('Undangku', $log->metadata['site_name']['to']);
        $this->assertArrayNotHasKey('registration_enabled', $log->metadata);
    }

    public function test_closing_registration_closes_the_sign_up_page(): void
    {
        app(SystemSettings::class)->save(['registration_enabled' => false], $this->admin(), app(AuditLogger::class));

        $this->get(route('register'))->assertOk()->assertSee('Registration is currently closed.');

        $this->post(route('register'), [
            'name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'Secret-password-123', 'password_confirmation' => 'Secret-password-123',
        ])->assertRedirect(route('register'));

        $this->assertGuest();
        $this->assertFalse(User::query()->where('email', 'ana@example.test')->exists());
    }

    public function test_new_accounts_get_the_default_timezone(): void
    {
        app(SystemSettings::class)->save(['default_timezone' => 'Asia/Jayapura'], $this->admin(), app(AuditLogger::class));

        $this->post(route('register'), [
            'name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'Secret-password-123', 'password_confirmation' => 'Secret-password-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('Asia/Jayapura', User::query()->where('email', 'ana@example.test')->value('timezone'));
    }
}
