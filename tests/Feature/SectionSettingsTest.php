<?php

namespace Tests\Feature;

use App\Livewire\SectionSettings;
use App\Models\Invitation;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class SectionSettingsTest extends TestCase
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

    public function test_field_changes_are_autosaved(): void
    {
        $invitation = $this->createInvitation();

        $this->settings($invitation, 'cover')
            ->set('config.title', 'Our Big Day')
            ->set('variant', 'framed')
            ->assertHasNoErrors()
            ->assertDispatched('invitation-updated');

        $this->assertSame('Our Big Day', $this->configOf($invitation, 'cover')['title']);
        $this->assertSame('framed', $invitation->sections()->where('type', 'cover')->value('variant'));
    }

    public function test_invalid_values_are_rejected_and_not_saved(): void
    {
        $invitation = $this->createInvitation(sections: ['maps']);

        $this->settings($invitation, 'maps')
            ->set('config.maps_url', 'javascript:alert(1)')
            ->assertHasErrors('config.maps_url')
            ->set('config.latitude', 123)
            ->assertHasErrors('config.latitude')
            ->set('variant', 'not-a-variant')
            ->assertHasErrors('variant');

        $this->assertSame('', $this->configOf($invitation, 'maps')['maps_url']);
    }

    public function test_repeater_items_can_be_added_reordered_and_removed(): void
    {
        $invitation = $this->createInvitation();

        $component = $this->settings($invitation, 'couple')
            ->call('addItem', 'people')
            ->set('config.people.0.name', 'John')
            ->call('addItem', 'people')
            ->set('config.people.1.name', 'Jane')
            ->call('sortItem', 'people', 1, 0);

        $this->assertSame(['Jane', 'John'], array_column($this->configOf($invitation, 'couple')['people'], 'name'));

        $component->call('removeItem', 'people', 0);

        $this->assertSame(['John'], array_column($this->configOf($invitation, 'couple')['people'], 'name'));
    }

    public function test_image_upload_is_resized_converted_to_webp_and_recorded(): void
    {
        Storage::fake('public');
        $invitation = $this->createInvitation();

        $this->settings($invitation, 'cover')
            ->set('uploads.background_image', UploadedFile::fake()->image('cover.jpg', 3000, 2000))
            ->assertHasNoErrors();

        $media = Media::query()->sole();
        $url = $this->configOf($invitation, 'cover')['background_image'];

        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame([1920, 1280], [$media->width, $media->height]);
        $this->assertSame($media->url(), $url);
        $this->assertStringEndsWith('.webp', $url);
        Storage::disk('public')->assertExists($media->path);
        $this->assertTrue($media->invitation->is($invitation));
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $invitation = $this->createInvitation();

        $this->settings($invitation, 'cover')
            ->set('uploads.background_image', UploadedFile::fake()->create('evil.php', 10, 'application/x-php'))
            ->assertHasErrors('uploads.background_image');

        $this->assertSame(0, Media::query()->count());
    }

    public function test_gallery_accepts_multiple_uploads_and_can_set_cover(): void
    {
        Storage::fake('public');
        $invitation = $this->createInvitation();

        $component = $this->settings($invitation, 'gallery')
            ->set('galleryUploads', [
                UploadedFile::fake()->image('one.jpg', 800, 600),
                UploadedFile::fake()->image('two.png', 600, 800),
            ])
            ->assertHasNoErrors();

        $images = $this->configOf($invitation, 'gallery')['images'];
        $this->assertCount(2, $images);

        $component->call('setAsCover', 'images', 1);

        $this->assertSame($images[1], $this->configOf($invitation, 'cover')['background_image']);
    }

    public function test_section_of_another_invitation_cannot_be_mounted(): void
    {
        $invitation = $this->createInvitation();
        $foreignSection = $this->createInvitation()->sections()->value('ulid');

        Livewire::actingAs($invitation->user)
            ->test(SectionSettings::class, ['invitationUlid' => $invitation->ulid, 'sectionUlid' => $foreignSection])
            ->assertNotFound();
    }
}
