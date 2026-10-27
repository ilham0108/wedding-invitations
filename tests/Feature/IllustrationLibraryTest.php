<?php

namespace Tests\Feature;

use App\Enums\IllustrationCategory;
use App\Livewire\IllustrationManager;
use App\Livewire\SectionSettings;
use App\Models\Illustration;
use App\Models\Invitation;
use App\Models\User;
use Database\Seeders\IllustrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class IllustrationLibraryTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function settings(Invitation $invitation, string $type): Testable
    {
        return Livewire::actingAs($invitation->user)->test(SectionSettings::class, [
            'invitationUlid' => $invitation->ulid,
            'sectionUlid' => $invitation->sections()->where('type', $type)->value('ulid'),
        ]);
    }

    private function configOf(Invitation $invitation, string $type): array
    {
        return $invitation->sections()->where('type', $type)->first()->configuration;
    }

    public function test_starter_set_is_seeded_once_with_real_files(): void
    {
        $this->seed(IllustrationSeeder::class);
        $this->seed(IllustrationSeeder::class);

        $this->assertSame(21, Illustration::query()->count());

        foreach (Illustration::query()->get() as $illustration) {
            $this->assertFileExists(public_path($illustration->path));
            $this->assertStringStartsWith('/illustrations/', $illustration->url());
        }

        foreach (IllustrationCategory::cases() as $category) {
            $this->assertGreaterThan(0, Illustration::query()->where('category', $category)->count());
        }
    }

    public function test_only_admins_manage_the_library(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/illustrations')->assertOk()->assertSeeLivewire(IllustrationManager::class);
        $this->actingAs(User::factory()->client()->create())->get('/admin/illustrations')->assertForbidden();

        Livewire::actingAs(User::factory()->client()->create())->test(IllustrationManager::class)->assertForbidden();
    }

    public function test_admin_uploads_are_converted_to_webp(): void
    {
        Storage::fake('public');

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(IllustrationManager::class)
            ->set('uploadCategory', 'story')
            ->set('uploadPremium', true)
            ->set('files', [UploadedFile::fake()->image('first-date.png', 800, 800)])
            ->call('saveUploads')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success', message: '1 illustration added.');

        $illustration = Illustration::query()->sole();

        $this->assertSame(IllustrationCategory::Story, $illustration->category);
        $this->assertSame('first-date', $illustration->name);
        $this->assertTrue($illustration->is_premium);
        $this->assertStringEndsWith('.webp', $illustration->path);
        $this->assertStringStartsWith('/storage/illustrations/story/', $illustration->url());
        Storage::disk('public')->assertExists($illustration->path);
    }

    public function test_non_images_and_svg_uploads_are_rejected(): void
    {
        Storage::fake('public');

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(IllustrationManager::class)
            ->set('files', [UploadedFile::fake()->createWithContent('art.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->call('saveUploads')
            ->assertHasErrors('files.0');

        $this->assertSame(0, Illustration::query()->count());
    }

    public function test_used_illustrations_are_hidden_instead_of_deleted(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $invitation = $this->createInvitation();

        $used = Illustration::factory()->category(IllustrationCategory::Cover)->create(['disk' => 'public', 'path' => 'illustrations/cover/used.webp']);
        $unused = Illustration::factory()->create(['disk' => 'public', 'path' => 'illustrations/person/unused.webp']);
        Storage::disk('public')->put($unused->path, 'x');

        $cover = $invitation->sections()->where('type', 'cover')->first();
        $cover->update(['configuration' => [...$cover->configuration, 'background_image' => $used->url()]]);

        Livewire::actingAs($admin)
            ->test(IllustrationManager::class)
            ->call('remove', $used->ulid)
            ->assertDispatched('notify', type: 'warning', message: 'This illustration is used by an invitation, so it was hidden from the picker instead of deleted.')
            ->call('remove', $unused->ulid)
            ->assertDispatched('notify', type: 'success', message: 'Illustration deleted.');

        $this->assertFalse($used->fresh()->is_active);
        $this->assertModelMissing($unused);
        Storage::disk('public')->assertMissing($unused->path);
    }

    public function test_client_picks_a_cover_illustration_in_the_editor(): void
    {
        $invitation = $this->createInvitation();
        $cover = Illustration::factory()->category(IllustrationCategory::Cover)->create(['name' => 'Floral Arch', 'path' => 'illustrations/cover-floral-arch.svg']);
        Illustration::factory()->create(['name' => 'Someone Else']);
        Illustration::factory()->category(IllustrationCategory::Cover)->hidden()->create(['name' => 'Retired Art']);

        $this->settings($invitation, 'cover')
            ->assertSee('Choose illustration')
            ->call('openPicker', 'background_image')
            ->assertSee('Floral Arch')
            ->assertDontSee('Someone Else')
            ->assertDontSee('Retired Art')
            ->call('pickIllustration', $cover->ulid)
            ->assertSet('pickerPath', null)
            ->assertDispatched('invitation-updated');

        $this->assertSame('/illustrations/cover-floral-arch.svg', $this->configOf($invitation, 'cover')['background_image']);
    }

    public function test_people_and_gallery_fields_use_their_own_categories(): void
    {
        $invitation = $this->createInvitation();
        $person = Illustration::factory()->create(['path' => 'illustrations/person-bride.svg']);
        $art = Illustration::factory()->category(IllustrationCategory::Gallery)->create(['path' => 'illustrations/gallery-cake.svg']);

        $this->settings($invitation, 'couple')
            ->call('addItem', 'people')
            ->call('openPicker', 'people.0.photo')
            ->call('pickIllustration', $person->ulid);

        $this->assertSame('/illustrations/person-bride.svg', $this->configOf($invitation, 'couple')['people'][0]['photo']);

        $this->settings($invitation, 'gallery')
            ->call('openPicker', 'images')
            ->call('pickIllustration', $art->ulid)
            ->call('openPicker', 'images')
            ->call('pickIllustration', $art->ulid);

        $this->assertSame(['/illustrations/gallery-cake.svg', '/illustrations/gallery-cake.svg'], $this->configOf($invitation, 'gallery')['images']);
    }

    public function test_wrong_category_hidden_art_and_non_image_fields_are_refused(): void
    {
        $invitation = $this->createInvitation();
        $person = Illustration::factory()->create();
        $hiddenCover = Illustration::factory()->category(IllustrationCategory::Cover)->hidden()->create();

        $this->settings($invitation, 'cover')->call('openPicker', 'title')->assertNotFound();

        $this->settings($invitation, 'cover')
            ->call('openPicker', 'background_image')
            ->call('pickIllustration', $person->ulid)
            ->assertNotFound();

        $this->settings($invitation, 'cover')
            ->call('openPicker', 'background_image')
            ->call('pickIllustration', $hiddenCover->ulid)
            ->assertNotFound();

        $this->assertSame('', (string) $this->configOf($invitation, 'cover')['background_image']);
    }

    public function test_published_invitation_shows_the_illustration(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'closing']);
        $invitation->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $cover = Illustration::factory()->category(IllustrationCategory::Cover)->create(['path' => 'illustrations/cover-sunset-hills.svg']);

        $this->settings($invitation, 'cover')->call('openPicker', 'background_image')->call('pickIllustration', $cover->ulid);

        $this->get('/invitation/'.$invitation->slug)->assertOk()->assertSee('/illustrations/cover-sunset-hills.svg', false);
    }
}
