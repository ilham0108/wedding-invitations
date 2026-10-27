<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\RsvpAttendance;
use App\Enums\RsvpStatus;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Rsvp;
use App\Notifications\RsvpReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class PublicRsvpTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $rsvpConfig
     */
    private function publishedInvitation(array $rsvpConfig = []): Invitation
    {
        $invitation = $this->createInvitation(sections: ['cover', 'rsvp', 'wishes']);
        $invitation->forceFill(['status' => InvitationStatus::Published, 'published_at' => now()])->save();

        $section = $invitation->sections()->where('type', 'rsvp')->first();
        $section->update(['configuration' => [...$section->configuration, ...$rsvpConfig]]);

        return $invitation;
    }

    private function rsvpUrl(Invitation $invitation): string
    {
        return '/invitation/'.$invitation->slug.'/rsvp';
    }

    public function test_public_page_renders_the_rsvp_form(): void
    {
        $invitation = $this->publishedInvitation();

        $this->get('/invitation/'.$invitation->slug)
            ->assertOk()
            ->assertSee('action="'.route('invitations.rsvp', $invitation->slug).'"', false)
            ->assertSee('Send RSVP');
    }

    public function test_anonymous_guest_can_rsvp_and_owner_is_notified(): void
    {
        Notification::fake();
        $invitation = $this->publishedInvitation();

        $this->from('/invitation/'.$invitation->slug)
            ->post($this->rsvpUrl($invitation), [
                'name' => 'Walk-in Friend',
                'attendance' => 'attending',
                'number_of_guests' => 2,
                'message' => 'Can not wait!',
            ])
            ->assertRedirect('/invitation/'.$invitation->slug.'#rsvp-form')
            ->assertSessionHas('rsvp_saved');

        $rsvp = Rsvp::query()->sole();
        $this->assertSame('Walk-in Friend', $rsvp->name);
        $this->assertSame(RsvpAttendance::Attending, $rsvp->attendance);
        $this->assertSame(2, $rsvp->number_of_guests);
        $this->assertNull($rsvp->guest_id);

        Notification::assertSentTo($invitation->user, RsvpReceived::class);
    }

    public function test_personal_link_rsvp_updates_the_guest_and_can_be_changed(): void
    {
        $invitation = $this->publishedInvitation();
        $guest = Guest::factory()->for($invitation)->create(['name' => 'Mr. Robert', 'number_of_guests' => 2]);

        $this->post($this->rsvpUrl($invitation), ['token' => $guest->token, 'name' => 'Spoofed Name', 'attendance' => 'attending', 'number_of_guests' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame(RsvpStatus::Attending, $guest->fresh()->rsvp_status);
        $this->assertSame('Mr. Robert', Rsvp::query()->sole()->name);

        $this->post($this->rsvpUrl($invitation), ['token' => $guest->token, 'attendance' => 'not_attending'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Rsvp::query()->count(), 'A guest keeps a single RSVP that is updated.');
        $this->assertSame(RsvpAttendance::NotAttending, Rsvp::query()->sole()->attendance);
        $this->assertSame(0, Rsvp::query()->sole()->number_of_guests);
        $this->assertSame(RsvpStatus::NotAttending, $guest->fresh()->rsvp_status);

        $this->flushSession();

        $this->get('/invitation/'.$invitation->slug.'/'.$guest->token)->assertSee('You replied: Not attending');
    }

    public function test_people_limit_comes_from_the_guest_or_section(): void
    {
        $invitation = $this->publishedInvitation(['max_guests' => 3]);
        $guest = Guest::factory()->for($invitation)->create(['number_of_guests' => 1]);

        $this->post($this->rsvpUrl($invitation), ['token' => $guest->token, 'attendance' => 'attending', 'number_of_guests' => 2])
            ->assertSessionHasErrorsIn('rsvp', 'number_of_guests');

        $this->post($this->rsvpUrl($invitation), ['name' => 'Anon', 'attendance' => 'attending', 'number_of_guests' => 4])
            ->assertSessionHasErrorsIn('rsvp', 'number_of_guests');

        $this->assertSame(0, Rsvp::query()->count());
    }

    public function test_rsvp_closes_after_the_deadline_in_the_event_timezone(): void
    {
        $this->travelTo(now('Asia/Jakarta')->setDate(2030, 6, 10)->setTime(23, 30));
        $invitation = $this->publishedInvitation(['deadline' => '2030-06-10', 'closed_message' => 'Sorry, RSVP closed.']);

        $this->post($this->rsvpUrl($invitation), ['name' => 'Just In Time', 'attendance' => 'attending', 'number_of_guests' => 1])
            ->assertSessionHasNoErrors();

        $this->travel(1)->hours();

        $this->post($this->rsvpUrl($invitation), ['name' => 'Too Late', 'attendance' => 'attending', 'number_of_guests' => 1])
            ->assertSessionHasErrorsIn('rsvp', 'attendance');

        $this->get('/invitation/'.$invitation->slug)->assertSee('Sorry, RSVP closed.')->assertDontSee('Send RSVP');
        $this->assertSame(['Just In Time'], Rsvp::query()->pluck('name')->all());
    }

    public function test_meal_preference_must_be_one_of_the_options(): void
    {
        $invitation = $this->publishedInvitation(['ask_meal_preference' => true, 'meal_options' => 'Beef, Chicken, Vegetarian']);

        $this->post($this->rsvpUrl($invitation), ['name' => 'A', 'attendance' => 'attending', 'number_of_guests' => 1, 'meal_preference' => 'Pizza'])
            ->assertSessionHasErrorsIn('rsvp', 'meal_preference');

        $this->post($this->rsvpUrl($invitation), ['name' => 'B', 'attendance' => 'attending', 'number_of_guests' => 1, 'meal_preference' => 'Chicken'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Chicken', Rsvp::query()->sole()->meal_preference);
    }

    public function test_spam_honeypot_invalid_token_and_disabled_section_are_rejected(): void
    {
        $invitation = $this->publishedInvitation();

        $this->post($this->rsvpUrl($invitation), ['name' => 'Bot', 'attendance' => 'attending', 'number_of_guests' => 1, 'website' => 'http://spam.test'])
            ->assertSessionHasErrorsIn('rsvp', 'website');

        $this->post($this->rsvpUrl($invitation), ['token' => str_repeat('a', 32), 'attendance' => 'attending', 'number_of_guests' => 1])
            ->assertSessionHasErrorsIn('rsvp', 'name');

        $invitation->sections()->where('type', 'rsvp')->update(['is_enabled' => false]);

        $this->post($this->rsvpUrl($invitation), ['name' => 'Late', 'attendance' => 'attending', 'number_of_guests' => 1])
            ->assertSessionHasErrorsIn('rsvp', 'attendance');

        $this->assertSame(0, Rsvp::query()->count());
    }

    public function test_draft_invitations_do_not_accept_rsvp(): void
    {
        $invitation = $this->createInvitation(sections: ['rsvp']);

        $this->post($this->rsvpUrl($invitation), ['name' => 'X', 'attendance' => 'attending', 'number_of_guests' => 1])->assertNotFound();
    }

    public function test_preview_shows_the_form_without_an_action(): void
    {
        $invitation = $this->publishedInvitation();

        $this->actingAs($invitation->user)
            ->get('/dashboard/invitations/'.$invitation->ulid.'/preview')
            ->assertOk()
            ->assertSee('Preview mode: responses are not saved.');
    }
}
