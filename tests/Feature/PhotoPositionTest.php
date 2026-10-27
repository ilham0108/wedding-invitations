<?php

namespace Tests\Feature;

use App\Livewire\SectionSettings;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\SectionRegistry;
use App\Support\PhotoStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class PhotoPositionTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function settings(Invitation $invitation, string $type): Testable
    {
        return Livewire::actingAs($invitation->user)->test(SectionSettings::class, [
            'invitationUlid' => $invitation->ulid,
            'sectionUlid' => $invitation->sections()->where('type', $type)->value('ulid'),
        ]);
    }

    private function publish(Invitation $invitation): void
    {
        $invitation->forceFill(['status' => 'published', 'published_at' => now()])->save();
    }

    public function test_focal_points_are_validated_before_they_reach_a_style_attribute(): void
    {
        $this->assertSame('30% 20%', PhotoStyle::focus('30% 20%'));
        $this->assertSame('100% 0%', PhotoStyle::focus(' 100% 0% '));
        $this->assertNull(PhotoStyle::focus('101% 0%'));
        $this->assertNull(PhotoStyle::focus('50% 50%; background: red'));
        $this->assertNull(PhotoStyle::focus(['50%', '50%']));

        $this->assertSame('object-position: 30% 20%; aspect-ratio: 1 / 1', PhotoStyle::image('30% 20%', 'square'));
        $this->assertSame('aspect-ratio: auto; height: auto', PhotoStyle::image(null, 'original'));
        $this->assertSame('', PhotoStyle::image('bad', 'unknown'));
        $this->assertSame('background-position: 10% 90%', PhotoStyle::background('10% 90%'));
    }

    public function test_every_cropped_image_field_gets_a_focus_field_with_its_visibility_rules(): void
    {
        $registry = app(SectionRegistry::class);
        $keys = fn (array $fields): array => array_column($fields, 'key');

        $story = collect($registry->fields('story'))->firstWhere('key', 'entries')['fields'];
        $this->assertSame(['date', 'title', 'description', 'icon', 'image', 'image_focus'], $keys($story));
        $this->assertSame('focus', $story[5]['type']);

        $cover = collect($registry->fields('cover'))->keyBy('key');
        $this->assertTrue($cover->has('background_image_focus'));
        $this->assertTrue($cover['desktop_image_focus']['admin_only']);
        $this->assertSame('desktop_panel', $cover['desktop_image_focus']['feature']);
        $this->assertNotContains('desktop_image_focus', $keys($registry->clientFields('cover')));

        $this->assertContains('background_photo_focus', $keys($registry->fields('closing')));
        $this->assertContains('photo_focus', $keys(collect($registry->fields('couple'))->firstWhere('key', 'people')['fields']));
    }

    public function test_clients_set_the_story_photo_position_and_shape_and_the_page_uses_them(): void
    {
        $invitation = $this->createInvitation(sections: ['story']);
        $this->publish($invitation);

        $component = $this->settings($invitation, 'story')
            ->assertSee(__('Photo shape'))
            ->call('addItem', 'entries')
            ->set('config.entries.0.title', 'First date')
            ->assertDontSee(__('Photo position'))
            ->set('config.entries.0.image', '/storage/story.webp')
            ->assertSee(__('Photo position'))
            ->set('config.entries.0.image_focus', '30% 20%')
            ->set('config.photo_ratio', 'square')
            ->assertHasNoErrors();

        $component->set('config.entries.0.image_focus', 'center; color: red')->assertHasErrors('config.entries.0.image_focus');

        $this->get(route('invitations.public', $invitation->slug))
            ->assertOk()
            ->assertSee('style="object-position: 30% 20%; aspect-ratio: 1 / 1"', false);
    }

    public function test_cover_and_portrait_positions_are_applied(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'couple']);
        $this->publish($invitation);

        $this->settings($invitation, 'cover')
            ->set('config.background_image', '/storage/cover.webp')
            ->set('config.background_image_focus', '40% 15%')
            ->assertHasNoErrors();

        $this->settings($invitation, 'couple')
            ->call('addItem', 'people')
            ->set('config.people.0.name', 'Ana')
            ->set('config.people.0.photo', '/storage/ana.webp')
            ->set('config.people.0.photo_focus', '50% 10%')
            ->assertHasNoErrors();

        $this->get(route('invitations.public', $invitation->slug))
            ->assertOk()
            ->assertSee('style="object-position: 40% 15%"', false)
            ->assertSee('object-position: 50% 10%', false);
    }

    public function test_the_mocha_glass_design_uses_positions_for_its_background_photos_and_story(): void
    {
        $template = InvitationTemplate::factory()->create([
            'design' => 'mocha-glass',
            'schema' => ['sections' => ['cover', 'story']],
            'default_configuration' => [
                'cover' => ['background_image' => '/storage/cover.webp', 'background_image_focus' => '25% 75%'],
                'story' => ['photo_ratio' => 'original', 'entries' => [['title' => 'Lamaran', 'image' => '/storage/lamaran.webp', 'image_focus' => '50% 0%']]],
            ],
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('templates.preview', $template))
            ->assertOk()
            ->assertSee('background-position: 25% 75%', false)
            ->assertSee('style="object-position: 50% 0%; aspect-ratio: auto; height: auto"', false);
    }
}
