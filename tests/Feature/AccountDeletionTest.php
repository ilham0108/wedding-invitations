<?php

namespace Tests\Feature;

use App\Enums\GuestMessageStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\AuditLog;
use App\Models\Guest;
use App\Models\GuestMessage;
use App\Models\Invitation;
use App\Models\Media;
use App\Models\Order;
use App\Models\Rsvp;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppMessage;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

/**
 * A client deleting their own account: data is gone for good, the email can sign up again,
 * and the user is warned with real numbers first.
 */
class AccountDeletionTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test here writes a photo; never onto the real public disk.
        Storage::fake(MediaService::DISK);
    }

    private function invitationWithData(User $owner): Invitation
    {
        $invitation = $this->createInvitation($owner);
        $guest = Guest::factory()->for($invitation)->create();
        Rsvp::factory()->for($invitation)->create(['guest_id' => $guest->id]);
        GuestMessage::factory()->for($invitation)->create(['status' => GuestMessageStatus::Approved]);
        $invitation->guestGroups()->create(['name' => 'Keluarga', 'sort_order' => 1]);

        $campaign = WhatsAppCampaign::factory()->create(['user_id' => $owner->id, 'invitation_id' => $invitation->id]);
        $campaign->recipients()->create(['name' => $guest->name, 'phone' => '6281234567890', 'message' => 'Hi', 'status' => WhatsAppRecipientStatus::Sent])
            ->attemptsLog()->create(['provider' => 'fonnte', 'attempt' => 1, 'success' => true]);

        $path = "invitations/{$invitation->ulid}/gallery/photo.webp";
        Storage::disk(MediaService::DISK)->put($path, 'image');
        (new Media(['invitation_id' => $invitation->id, 'collection' => 'gallery', 'disk' => MediaService::DISK, 'path' => $path, 'mime_type' => 'image/webp', 'size' => 5]))
            ->forceFill(['user_id' => $owner->id])->save();

        return $invitation;
    }

    public function test_the_warning_lists_what_will_be_deleted(): void
    {
        $user = User::factory()->client()->create();
        $this->invitationWithData($user);
        Guest::factory()->for($user->invitations()->first())->count(2)->create();

        $this->actingAs($user)->get('/dashboard/settings')
            ->assertOk()
            ->assertSee('1 invitation, with its sections, photos, and music')
            ->assertSee('3 guests, with their personal links, RSVPs, and wishes')
            ->assertSee('Export your guest list first')
            ->assertSee('name="confirm_permanent"', false);
    }

    public function test_deleting_requires_ticking_the_confirmation(): void
    {
        $user = User::factory()->client()->create();
        $invitation = $this->invitationWithData($user);

        $this->actingAs($user)->from('/dashboard/settings')
            ->delete('/dashboard/settings', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'confirm_permanent')
            ->assertRedirect('/dashboard/settings');

        $this->assertAuthenticated();
        $this->assertNotNull($invitation->fresh());
    }

    public function test_everything_is_removed_for_good_and_the_email_can_sign_up_again(): void
    {
        $user = User::factory()->client()->create(['email' => 'pasangan@example.com', 'phone' => '6281200000000']);
        $invitation = $this->invitationWithData($user);
        $invitation->delete(); // already in the trash: still removed for good
        $order = Order::factory()->create(['user_id' => $user->id]);

        $other = User::factory()->client()->create();
        $othersInvitation = $this->invitationWithData($other);

        $this->actingAs($user)
            ->delete('/dashboard/settings', ['password' => 'password', 'confirm_permanent' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull(Invitation::withTrashed()->find($invitation->id));
        foreach ([Guest::withTrashed(), GuestMessage::withTrashed(), Rsvp::query(), Media::withTrashed()] as $query) {
            $this->assertSame(0, (clone $query)->where('invitation_id', $invitation->id)->count());
        }
        $this->assertSame(0, WhatsAppCampaign::query()->where('user_id', $user->id)->count());
        $this->assertFalse(Storage::disk(MediaService::DISK)->exists("invitations/{$invitation->ulid}/gallery/photo.webp"));

        // Payment record kept, linked to a row that no longer identifies the person.
        $kept = User::withTrashed()->find($user->id);
        $this->assertNotNull($order->fresh());
        $this->assertTrue($kept->trashed());
        $this->assertNotSame('pasangan@example.com', $kept->email);
        $this->assertNull($kept->phone);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.self_deleted', 'auditable_id' => $user->id]);
        $this->assertEquals(['invitations' => 1, 'guests' => 1], AuditLog::query()->where('action', 'user.self_deleted')->value('metadata'));

        // Another client's data is untouched.
        $this->assertSame(1, $othersInvitation->guests()->count());
        $this->assertSame(1, WhatsAppMessage::query()->count());

        // The same email can sign up again.
        $this->post('/register', [
            'name' => 'Pasangan Baru',
            'email' => 'pasangan@example.com',
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, User::query()->where('email', 'pasangan@example.com')->count());
    }

    public function test_an_account_without_a_password_confirms_with_the_box_only(): void
    {
        $user = User::factory()->client()->create(['password' => null, 'google_id' => 'google-123']);

        $this->actingAs($user)
            ->delete('/dashboard/settings', ['confirm_permanent' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull(User::withTrashed()->find($user->id)->google_id, 'Signing in with Google later creates a fresh account.');
    }
}
