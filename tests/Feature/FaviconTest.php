<?php

namespace Tests\Feature;

use App\Livewire\SystemSettingsForm;
use App\Models\User;
use App\Services\FaviconService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_no_icon_links_are_rendered_until_an_icon_is_uploaded(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('rel="icon"', false);
        $this->getJson('/site.webmanifest')->assertOk()->assertJsonPath('icons', []);
    }

    public function test_admin_upload_creates_every_size_cropped_to_a_square(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(SystemSettingsForm::class)
            ->set('favicon', UploadedFile::fake()->image('icon.png', 800, 600))
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $iconUrl = app(FaviconService::class)->url(32);
        $this->assertNotNull($iconUrl);

        foreach (FaviconService::SIZES as $size) {
            $path = collect(Storage::disk('public')->allFiles('branding/favicon'))->first(fn (string $file): bool => str_ends_with($file, "icon-{$size}.png"));

            $this->assertNotNull($path, "Missing {$size}px icon");
            $this->assertSame([$size, $size], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
        }

        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'settings.updated']);
    }

    public function test_icon_is_linked_on_the_site_and_in_the_manifest(): void
    {
        app(FaviconService::class)->replace(UploadedFile::fake()->image('icon.png', 512, 512), User::factory()->superAdmin()->create());
        $favicon = app(FaviconService::class);

        $this->get('/login')->assertOk()
            ->assertSee('<link rel="icon" type="image/png" sizes="32x32" href="'.$favicon->url(32).'">', false)
            ->assertSee($favicon->url(180), false)
            ->assertSee(route('site.manifest'), false);

        $this->getJson('/site.webmanifest')->assertOk()
            ->assertJsonPath('name', config('app.name'))
            ->assertJsonPath('icons.0.src', $favicon->url(192))
            ->assertJsonPath('icons.1.sizes', '512x512');
    }

    public function test_replacing_and_removing_cleans_up_old_files(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $favicon = app(FaviconService::class);

        $favicon->replace(UploadedFile::fake()->image('first.png', 512, 512), $admin);
        $first = collect(Storage::disk('public')->directories('branding/favicon'))->sole();

        $favicon->replace(UploadedFile::fake()->image('second.png', 512, 512), $admin);
        $second = collect(Storage::disk('public')->directories('branding/favicon'))->sole();
        $this->assertNotSame($first, $second);

        Livewire::actingAs($admin)->test(SystemSettingsForm::class)->call('removeFavicon');

        $this->assertSame([], Storage::disk('public')->directories('branding/favicon'));
        $this->assertFalse(app(FaviconService::class)->exists());
    }

    public function test_icon_must_be_an_image_of_sufficient_size(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(SystemSettingsForm::class)
            ->set('favicon', UploadedFile::fake()->image('tiny.png', 64, 64))
            ->assertHasErrors('favicon')
            ->set('favicon', UploadedFile::fake()->create('icon.svg', 10, 'image/svg+xml'))
            ->assertHasErrors('favicon');

        $this->assertFalse(app(FaviconService::class)->exists());
    }

    public function test_saving_other_settings_cannot_change_the_icon_path(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(SystemSettingsForm::class)
            ->set('settings.favicon', 'branding/favicon/../../secret')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(app(FaviconService::class)->exists());
    }

    public function test_clients_cannot_upload_an_icon(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(SystemSettingsForm::class)
            ->assertForbidden();
    }
}
