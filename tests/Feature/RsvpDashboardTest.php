<?php

namespace Tests\Feature;

use App\Enums\RsvpStatus;
use App\Livewire\RsvpDashboard;
use App\Models\Guest;
use App\Models\Rsvp;
use App\Models\User;
use App\Services\Rsvp\RsvpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class RsvpDashboardTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    public function test_stats_and_breakdowns_are_computed(): void
    {
        $invitation = $this->createInvitation();
        $family = $invitation->guestGroups()->create(['name' => 'Family']);
        $robert = Guest::factory()->for($invitation)->create(['guest_group_id' => $family->id, 'rsvp_status' => RsvpStatus::Attending]);
        Guest::factory()->for($invitation)->create(['rsvp_status' => RsvpStatus::NotAttending]);
        Guest::factory()->count(2)->for($invitation)->create();

        Rsvp::factory()->for($invitation)->for($robert)->create(['number_of_guests' => 3]);
        Rsvp::factory()->for($invitation)->create(['number_of_guests' => 2]);
        Rsvp::factory()->for($invitation)->notAttending()->create();

        $service = app(RsvpService::class);

        $this->assertSame(['invited' => 4, 'attending' => 2, 'not_attending' => 1, 'pending' => 2, 'people' => 5], $service->stats($invitation));
        $this->assertSame(['Family' => 3, 'Public link' => 2], $service->peopleByGroup($invitation));

        $perDay = $service->responsesPerDay($invitation);
        $this->assertCount(30, $perDay);
        $this->assertSame(3, end($perDay));
    }

    public function test_dashboard_page_renders_charts_and_responses(): void
    {
        $invitation = $this->createInvitation();
        Rsvp::factory()->for($invitation)->create(['name' => 'Responder One', 'message' => 'See you there']);

        $this->actingAs($invitation->user)
            ->get('/dashboard/rsvp')
            ->assertOk()
            ->assertSeeLivewire(RsvpDashboard::class)
            ->assertSee('Attendance')
            ->assertSee('Responder One');

        $this->actingAs($invitation->user)
            ->get('/dashboard/rsvp?tab=guestbook')
            ->assertOk()
            ->assertSee('Auto-approve new wishes');
    }

    public function test_responses_can_be_filtered(): void
    {
        $invitation = $this->createInvitation();
        Rsvp::factory()->for($invitation)->create(['name' => 'Coming Carla']);
        Rsvp::factory()->for($invitation)->notAttending()->create(['name' => 'Absent Andy']);

        Livewire::actingAs($invitation->user)
            ->test(RsvpDashboard::class, ['invitationUlid' => $invitation->ulid])
            ->set('attendance', 'not_attending')
            ->assertSee('Absent Andy')
            ->assertDontSee('Coming Carla')
            ->set('attendance', '')
            ->set('search', 'carla')
            ->assertSee('Coming Carla')
            ->assertDontSee('Absent Andy');
    }

    public function test_other_users_cannot_see_responses(): void
    {
        $invitation = $this->createInvitation();
        Rsvp::factory()->for($invitation)->create(['name' => 'Private Person']);

        $this->actingAs(User::factory()->client()->create())
            ->get('/dashboard/rsvp?invitation='.$invitation->ulid)
            ->assertOk()
            ->assertDontSee('Private Person');

        Livewire::actingAs(User::factory()->client()->create())
            ->test(RsvpDashboard::class, ['invitationUlid' => $invitation->ulid])
            ->assertForbidden();
    }

    public function test_rsvp_notification_appears_in_the_top_bar_and_can_be_marked_read(): void
    {
        $invitation = $this->createInvitation();
        $invitation->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $invitation->sections()->create(['type' => 'rsvp', 'sort_order' => 99, 'is_enabled' => true, 'configuration' => []]);

        $this->post('/invitation/'.$invitation->slug.'/rsvp', ['name' => 'Notifier Nina', 'attendance' => 'attending', 'number_of_guests' => 1]);

        $this->actingAs($invitation->user)->get('/dashboard')->assertSee('Notifier Nina will attend (1 person).');

        $this->actingAs($invitation->user)->post('/dashboard/notifications/read')->assertRedirect();

        $this->assertSame(0, $invitation->user->unreadNotifications()->count());
    }
}
