<?php

namespace Tests\Feature;

use App\Livewire\GuestTable;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class GuestManagementTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function table(Invitation $invitation): Testable
    {
        return Livewire::actingAs($invitation->user)->test(GuestTable::class, ['invitationUlid' => $invitation->ulid]);
    }

    public function test_guest_page_shows_empty_state_and_table(): void
    {
        $invitation = $this->createInvitation();

        $this->actingAs($invitation->user)
            ->get('/dashboard/guests')
            ->assertOk()
            ->assertSeeLivewire(GuestTable::class)
            ->assertSee('Start building your guest list.');
    }

    public function test_guest_page_ignores_invitations_owned_by_someone_else(): void
    {
        $mine = $this->createInvitation();
        $theirs = $this->createInvitation();
        $theirs->update(['title' => 'Their Secret Party']);

        $this->actingAs($mine->user)
            ->get('/dashboard/guests?invitation='.$theirs->ulid)
            ->assertOk()
            ->assertDontSee('Their Secret Party');
    }

    public function test_table_cannot_be_mounted_for_a_foreign_invitation(): void
    {
        $theirs = $this->createInvitation();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(GuestTable::class, ['invitationUlid' => $theirs->ulid])
            ->assertForbidden();
    }

    public function test_guest_can_be_created_edited_and_deleted(): void
    {
        $invitation = $this->createInvitation();

        $this->table($invitation)
            ->call('create')
            ->set('form.name', 'Robert Smith')
            ->set('form.phone', '0812 3456 7890')
            ->set('form.group', 'Family')
            ->set('form.number_of_guests', 2)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Robert Smith');

        $guest = Guest::query()->sole();
        $this->assertSame('6281234567890', $guest->phone);
        $this->assertSame('Family', $guest->group->name);

        $this->table($invitation)
            ->call('edit', $guest->ulid)
            ->assertSet('form.name', 'Robert Smith')
            ->set('form.name', 'Mr. Robert Smith')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Mr. Robert Smith', $guest->fresh()->name);

        $this->table($invitation)->call('delete', $guest->ulid);

        $this->assertSoftDeleted($guest);
        $this->assertDatabaseHas('audit_logs', ['action' => 'guest.deleted', 'auditable_id' => $invitation->id]);
    }

    public function test_guest_form_validates_and_blocks_duplicate_phone(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create(['phone' => '6281234567890']);

        $this->table($invitation)
            ->call('create')
            ->set('form.name', '')
            ->set('form.phone', '123')
            ->call('save')
            ->assertHasErrors(['name', 'phone'])
            ->set('form.name', 'Someone')
            ->set('form.phone', '0812-3456-7890')
            ->call('save')
            ->assertHasErrors('phone');

        $this->assertSame(1, $invitation->guests()->count());
    }

    public function test_same_phone_is_allowed_in_another_invitation(): void
    {
        $first = $this->createInvitation();
        $second = $this->createInvitation($first->user);
        Guest::factory()->for($first)->create(['phone' => '6281234567890']);

        $this->table($second)
            ->call('create')
            ->set('form.name', 'Robert')
            ->set('form.phone', '081234567890')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_bulk_assign_group_and_bulk_delete(): void
    {
        $invitation = $this->createInvitation();
        $guests = Guest::factory()->count(3)->for($invitation)->create();
        $vip = $invitation->guestGroups()->create(['name' => 'VIP']);

        $this->table($invitation)
            ->set('selected', $guests->take(2)->pluck('ulid')->all())
            ->set('bulkGroup', $vip->ulid)
            ->call('bulkAssignGroup')
            ->assertSet('selected', []);

        $this->assertSame(2, $vip->guests()->count());

        $this->table($invitation)
            ->set('selected', $guests->pluck('ulid')->all())
            ->call('bulkDelete');

        $this->assertSame(0, $invitation->guests()->count());
    }

    public function test_foreign_guest_ulids_are_ignored_or_not_found(): void
    {
        $invitation = $this->createInvitation();
        $foreignGuest = Guest::factory()->for($this->createInvitation())->create();

        $this->table($invitation)->call('edit', $foreignGuest->ulid)->assertNotFound();

        $this->table($invitation)->set('selected', [$foreignGuest->ulid])->call('bulkDelete');

        $this->assertNotSoftDeleted($foreignGuest);
    }

    public function test_search_filter_and_sort(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create(['name' => 'Alice', 'number_of_guests' => 3]);
        Guest::factory()->for($invitation)->create(['name' => 'Bob', 'number_of_guests' => 1]);

        $this->table($invitation)
            ->set('search', 'ali')
            ->assertSee('Alice')
            ->assertDontSee('Bob')
            ->set('search', '')
            ->call('sortBy', 'number_of_guests')
            ->assertSeeInOrder(['Bob', 'Alice'])
            ->call('sortBy', 'number_of_guests')
            ->assertSeeInOrder(['Alice', 'Bob'])
            ->call('sortBy', 'id; drop table guests')
            ->assertSet('sortField', 'number_of_guests');
    }

    public function test_groups_can_be_added_and_deleted_without_deleting_guests(): void
    {
        $invitation = $this->createInvitation();

        $this->table($invitation)->set('newGroupName', 'Friends')->call('addGroup')->assertSee('Friends');

        $group = $invitation->guestGroups()->sole();
        $guest = Guest::factory()->for($invitation)->create(['guest_group_id' => $group->id]);

        $this->table($invitation)->call('deleteGroup', $group->ulid);

        $this->assertModelMissing($group);
        $this->assertNull($guest->fresh()->guest_group_id);
    }
}
