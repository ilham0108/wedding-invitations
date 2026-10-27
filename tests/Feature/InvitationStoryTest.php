<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Livewire\InvitationStory;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Invitation\StoryCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

class InvitationStoryTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, RefreshDatabase;

    private function published(): Invitation
    {
        $invitation = $this->createInvitation(sections: ['cover', 'event']);
        $invitation->forceFill(['status' => InvitationStatus::Published, 'published_at' => now()])->save();

        return $invitation;
    }

    private function card(Invitation $invitation): array
    {
        return app(StoryCard::class)->forInvitation($invitation->fresh())['card'];
    }

    public function test_owners_open_the_story_page(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'event']);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.story', $invitation))
            ->assertOk()
            ->assertSeeLivewire(InvitationStory::class)
            ->assertSee('Download image')
            ->assertSee('fonts.bunny.net/css?family=', false);
    }

    public function test_other_users_cannot_open_it(): void
    {
        $invitation = $this->createInvitation();

        $this->actingAs(User::factory()->client()->create())
            ->get(route('dashboard.invitations.story', $invitation))
            ->assertForbidden();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(InvitationStory::class, ['invitationUlid' => $invitation->ulid])
            ->assertForbidden();
    }

    public function test_the_card_uses_the_cover_and_first_dated_event(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'event']);
        $invitation->sections()->where('type', 'cover')->first()->update(['configuration' => [
            'title' => 'Romeo & Juliet', 'subtitle' => 'The Wedding of', 'date_text' => '', 'background_image' => '/storage/cover.webp',
        ]]);
        $invitation->sections()->where('type', 'event')->first()->update(['configuration' => ['events' => [
            ['title' => 'Akad', 'date' => '', 'venue' => 'Nowhere'],
            ['title' => 'Resepsi', 'date' => '2027-06-12', 'venue' => 'Grand Ballroom'],
        ]]]);

        $card = $this->card($invitation);

        $this->assertSame('Romeo & Juliet', $card['names']);
        $this->assertSame('The Wedding of', $card['subtitle']);
        $this->assertSame('Grand Ballroom', $card['venue']);
        $this->assertNotSame('', $card['date']);
        $this->assertSame('/storage/cover.webp', $card['photo']);
        $this->assertStringEndsWith('/invitation/'.$invitation->slug, $card['link']);
        $this->assertStringNotContainsString('://', $card['link']);
        $this->assertSame([1080, 1920], [$card['width'], $card['height']]);
    }

    public function test_branding_follows_the_plan(): void
    {
        $this->freePlan(['remove_branding' => false]);
        $invitation = $this->createInvitation();

        $this->assertNotNull($this->card($invitation)['branding']);

        $this->subscribe($invitation->user, ['remove_branding' => true]);
        $this->assertNull($this->card($invitation)['branding']);
    }

    public function test_owners_save_layout_text_and_the_guest_button(): void
    {
        $invitation = $this->createInvitation();

        Livewire::actingAs($invitation->user)
            ->test(InvitationStory::class, ['invitationUlid' => $invitation->ulid])
            ->assertSee('Save the Date')
            ->call('save', [
                'layout' => 'minimal',
                'headline' => "Save\nthe Date ".str_repeat('x', 100),
                'message' => 'See you there',
                'guest_button' => true,
                'link' => false,
                'evil' => '<script>',
            ])
            ->assertHasNoErrors();

        $story = $invitation->settings()->first()->story;
        $this->assertSame('minimal', $story['layout']);
        $this->assertStringStartsWith('Save the Date', $story['headline']);
        $this->assertSame(StoryCard::MAX_HEADLINE, mb_strlen($story['headline']));
        $this->assertSame('See you there', $story['message']);
        $this->assertTrue($story['guest_button']);
        $this->assertFalse($story['link']);
        $this->assertArrayNotHasKey('evil', $story);

        app(StoryCard::class)->save($invitation, ['layout' => 'collage']);
        $this->assertSame('photo', $invitation->settings()->first()->story['layout']);
    }

    public function test_guests_see_the_story_button_only_when_the_owner_allows_it(): void
    {
        $invitation = $this->published();

        $this->get(route('invitations.public', $invitation->slug))->assertOk()->assertDontSee('Share to Instagram Story');

        app(StoryCard::class)->save($invitation, ['guest_button' => true, 'headline' => 'Save the Date']);

        $this->get(route('invitations.public', $invitation->slug))
            ->assertOk()
            ->assertSee('Share to Instagram Story')
            ->assertSee('Save the Date');
    }

    public function test_the_guest_image_never_carries_a_personal_link(): void
    {
        $invitation = $this->published();
        app(StoryCard::class)->save($invitation, ['guest_button' => true]);
        $guest = Guest::factory()->for($invitation)->create();

        $html = $this->get(route('invitations.personal', [$invitation->slug, $guest->token]))->assertOk()->getContent();

        $this->assertStringContainsString('Share to Instagram Story', $html);
        $this->assertStringNotContainsString($guest->token, $html);
    }
}
