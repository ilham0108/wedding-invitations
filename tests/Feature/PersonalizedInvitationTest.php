<?php

namespace Tests\Feature;

use App\Enums\GuestInvitationStatus;
use App\Enums\InvitationStatus;
use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class PersonalizedInvitationTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function publishedInvitation(): Invitation
    {
        $invitation = $this->createInvitation(sections: ['cover', 'closing']);
        $invitation->forceFill(['status' => InvitationStatus::Published, 'published_at' => now()])->save();

        return $invitation;
    }

    public function test_personal_link_greets_the_guest_and_marks_it_opened(): void
    {
        $invitation = $this->publishedInvitation();
        $guest = Guest::factory()->for($invitation)->create(['name' => 'Mr. Robert']);

        $this->get('/invitation/'.$invitation->slug.'/'.$guest->token)
            ->assertOk()
            ->assertSee('Dear')
            ->assertSee('Mr. Robert')
            ->assertHeader('Cache-Control', 'no-store, private');

        $guest->refresh();
        $this->assertSame(GuestInvitationStatus::Opened, $guest->invitation_status);
        $this->assertNotNull($guest->opened_at);
    }

    public function test_guest_name_is_escaped(): void
    {
        $invitation = $this->publishedInvitation();
        $guest = Guest::factory()->for($invitation)->create(['name' => '<script>alert(1)</script>']);

        $this->get('/invitation/'.$invitation->slug.'/'.$guest->token)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_unknown_or_foreign_tokens_show_the_generic_invitation(): void
    {
        $invitation = $this->publishedInvitation();
        $otherGuest = Guest::factory()->for($this->publishedInvitation())->create(['name' => 'Other Party Guest']);

        $this->get('/invitation/'.$invitation->slug.'/'.str_repeat('x', 32))
            ->assertOk()
            ->assertDontSee('Dear');

        $this->get('/invitation/'.$invitation->slug.'/'.$otherGuest->token)
            ->assertOk()
            ->assertDontSee('Other Party Guest');
    }

    public function test_deleted_guest_token_no_longer_personalizes(): void
    {
        $invitation = $this->publishedInvitation();
        $guest = Guest::factory()->for($invitation)->create(['name' => 'Removed Guest']);
        $guest->delete();

        $this->get('/invitation/'.$invitation->slug.'/'.$guest->token)
            ->assertOk()
            ->assertDontSee('Removed Guest');
    }

    public function test_personal_link_of_a_draft_invitation_is_not_found(): void
    {
        $invitation = $this->createInvitation();
        $guest = Guest::factory()->for($invitation)->create();

        $this->get('/invitation/'.$invitation->slug.'/'.$guest->token)->assertNotFound();
        $this->assertSame(GuestInvitationStatus::NotSent, $guest->fresh()->invitation_status);
    }
}
