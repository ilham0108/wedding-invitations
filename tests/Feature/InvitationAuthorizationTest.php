<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * rules.md section 89: User A must get 403 on User B's invitation even when the ULID is known.
 */
class InvitationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_preview_own_invitation(): void
    {
        $invitation = Invitation::factory()->for(User::factory()->client())->create();

        $this->actingAs($invitation->user)
            ->get('/dashboard/invitations/'.$invitation->ulid.'/preview')
            ->assertOk()
            ->assertSee('Preview: '.$invitation->title);
    }

    public function test_other_client_gets_forbidden_on_preview_and_delete(): void
    {
        $invitation = Invitation::factory()->for(User::factory()->client())->create();
        $intruder = User::factory()->client()->create();

        $this->actingAs($intruder)->get('/dashboard/invitations/'.$invitation->ulid.'/preview')->assertForbidden();
        $this->actingAs($intruder)->delete('/dashboard/invitations/'.$invitation->ulid)->assertForbidden();

        $this->assertNotSoftDeleted($invitation);
    }

    public function test_internal_numeric_id_cannot_be_used_in_urls(): void
    {
        $invitation = Invitation::factory()->for(User::factory()->client())->create();

        $this->actingAs($invitation->user)
            ->get('/dashboard/invitations/'.$invitation->id.'/preview')
            ->assertNotFound();
    }

    public function test_invitation_list_only_shows_own_invitations(): void
    {
        $mine = Invitation::factory()->for(User::factory()->client())->create(['title' => 'Mine Wedding']);
        Invitation::factory()->for(User::factory()->client())->create(['title' => 'Someone Else Party']);

        $this->actingAs($mine->user)
            ->get('/dashboard/invitations')
            ->assertOk()
            ->assertSee('Mine Wedding')
            ->assertDontSee('Someone Else Party');
    }

    public function test_owner_can_soft_delete_invitation(): void
    {
        $invitation = Invitation::factory()->for(User::factory()->client())->create();

        $this->actingAs($invitation->user)
            ->delete('/dashboard/invitations/'.$invitation->ulid)
            ->assertRedirect(route('dashboard.invitations.index'));

        $this->assertSoftDeleted($invitation);
    }

    public function test_super_admin_can_preview_any_invitation(): void
    {
        $invitation = Invitation::factory()->for(User::factory()->client())->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/dashboard/invitations/'.$invitation->ulid.'/preview')
            ->assertOk();
    }

    public function test_user_without_role_cannot_create_invitations(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard/invitations/create')
            ->assertForbidden();
    }
}
