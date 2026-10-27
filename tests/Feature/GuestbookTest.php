<?php

namespace Tests\Feature;

use App\Enums\GuestMessageStatus;
use App\Enums\InvitationStatus;
use App\Livewire\GuestbookModeration;
use App\Models\GuestMessage;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class GuestbookTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function publishedInvitation(): Invitation
    {
        $invitation = $this->createInvitation(sections: ['cover', 'wishes']);
        $invitation->forceFill(['status' => InvitationStatus::Published, 'published_at' => now()])->save();

        return $invitation;
    }

    public function test_new_wishes_wait_for_approval_by_default(): void
    {
        $invitation = $this->publishedInvitation();

        $this->post('/invitation/'.$invitation->slug.'/wishes', ['name' => 'Aunt May', 'message' => 'Congratulations!'])
            ->assertSessionHas('wish_saved', 'pending');

        $this->assertSame(GuestMessageStatus::Pending, GuestMessage::query()->sole()->status);
        $this->get('/invitation/'.$invitation->slug)->assertDontSee('Congratulations!');
    }

    public function test_auto_approve_publishes_wishes_immediately(): void
    {
        $invitation = $this->publishedInvitation();
        $invitation->settings->update(['guestbook_auto_approve' => true]);

        $this->post('/invitation/'.$invitation->slug.'/wishes', ['name' => 'Uncle Ben', 'message' => 'Best wishes <b>always</b>'])
            ->assertSessionHas('wish_saved', 'approved');

        $this->get('/invitation/'.$invitation->slug)
            ->assertSee('Best wishes &lt;b&gt;always&lt;/b&gt;', false)
            ->assertSee('Uncle Ben');
    }

    public function test_owner_can_approve_hide_and_delete_wishes(): void
    {
        $invitation = $this->publishedInvitation();
        $pending = GuestMessage::factory()->for($invitation)->create(['message' => 'Pending wish']);
        $approved = GuestMessage::factory()->for($invitation)->approved()->create(['message' => 'Visible wish']);

        Livewire::actingAs($invitation->user)
            ->test(GuestbookModeration::class, ['invitationUlid' => $invitation->ulid])
            ->assertSee('Pending wish')
            ->call('approve', $pending->ulid)
            ->call('hide', $approved->ulid)
            ->set('autoApprove', true);

        $this->assertSame(GuestMessageStatus::Approved, $pending->fresh()->status);
        $this->assertSame(GuestMessageStatus::Hidden, $approved->fresh()->status);
        $this->assertTrue($invitation->settings()->value('guestbook_auto_approve'));

        $this->get('/invitation/'.$invitation->slug)->assertSee('Pending wish')->assertDontSee('Visible wish');

        Livewire::actingAs($invitation->user)
            ->test(GuestbookModeration::class, ['invitationUlid' => $invitation->ulid])
            ->call('delete', $pending->ulid);

        $this->assertSoftDeleted($pending);
    }

    public function test_messages_of_other_invitations_cannot_be_moderated(): void
    {
        $invitation = $this->publishedInvitation();
        $foreign = GuestMessage::factory()->for($this->publishedInvitation())->create();

        Livewire::actingAs($invitation->user)
            ->test(GuestbookModeration::class, ['invitationUlid' => $invitation->ulid])
            ->call('approve', $foreign->ulid)
            ->assertNotFound();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(GuestbookModeration::class, ['invitationUlid' => $invitation->ulid])
            ->assertForbidden();

        $this->assertSame(GuestMessageStatus::Pending, $foreign->fresh()->status);
    }

    public function test_wishes_require_a_message_and_reject_bots(): void
    {
        $invitation = $this->publishedInvitation();

        $this->post('/invitation/'.$invitation->slug.'/wishes', ['name' => 'X', 'message' => ''])->assertSessionHasErrorsIn('wishes', 'message');
        $this->post('/invitation/'.$invitation->slug.'/wishes', ['name' => 'X', 'message' => 'Hi', 'website' => 'spam'])->assertSessionHasErrorsIn('wishes', 'website');

        $this->assertSame(0, GuestMessage::query()->count());
    }
}
