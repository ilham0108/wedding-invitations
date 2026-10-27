<?php

namespace Tests\Feature;

use App\Livewire\SystemSettingsForm;
use App\Models\User;
use App\Services\LogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_name_is_shown_when_the_logo_file_is_missing(): void
    {
        config(['branding.logo' => 'images/does-not-exist.svg']);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('does-not-exist.svg')
            ->assertSee(config('app.name'));
    }

    public function test_logo_image_is_shown_in_headers_when_the_file_exists(): void
    {
        config(['branding.logo' => 'images/demo/cover.svg']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('src="'.asset('images/demo/cover.svg').'?v=', false)
            ->assertSee('alt="'.config('app.name').'"', false);
    }

    public function test_the_marketing_site_uses_the_leaf_wordmark(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('Digital Invitation');
    }

    public function test_admin_uploaded_logo_is_used_everywhere_including_the_marketing_site(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(SystemSettingsForm::class)
            ->set('logo', UploadedFile::fake()->image('logo.png', 600, 200))
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $url = app(LogoService::class)->url();
        $path = collect(Storage::disk('public')->allFiles('branding/logo'))->sole();

        $this->assertStringEndsWith($path, $url);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'settings.updated']);

        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('src="'.$url.'"', false);
        $this->get('/')->assertOk()->assertSee('src="'.$url.'"', false)->assertDontSee('Digital Invitation');
    }

    public function test_removing_the_uploaded_logo_restores_the_defaults(): void
    {
        Storage::fake('public');
        config(['branding.logo' => 'images/demo/cover.svg']);
        $admin = User::factory()->superAdmin()->create();

        app(LogoService::class)->replace(UploadedFile::fake()->image('logo.png', 600, 200), $admin);
        app(LogoService::class)->replace(UploadedFile::fake()->image('new.png', 600, 200), $admin);
        $this->assertCount(1, Storage::disk('public')->allFiles('branding/logo'));

        Livewire::actingAs($admin)->test(SystemSettingsForm::class)->call('removeLogo');

        $this->assertSame([], Storage::disk('public')->allFiles('branding/logo'));
        auth()->logout();
        $this->get('/login')->assertSee('src="'.asset('images/demo/cover.svg').'?v=', false);
        $this->get('/')->assertSee('Digital Invitation');
    }

    public function test_logo_upload_rejects_svg_and_tiny_images(): void
    {
        Storage::fake('public');

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(SystemSettingsForm::class)
            ->set('logo', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
            ->assertHasErrors('logo')
            ->set('logo', UploadedFile::fake()->image('tiny.png', 100, 20))
            ->assertHasErrors('logo');

        $this->assertFalse(app(LogoService::class)->exists());
    }
}
