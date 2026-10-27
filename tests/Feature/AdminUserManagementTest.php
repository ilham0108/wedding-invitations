<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Livewire\UserDetail;
use App\Livewire\UserManager;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    private function published(User $owner): Invitation
    {
        $invitation = $this->createInvitation($owner);
        $invitation->forceFill(['status' => InvitationStatus::Published, 'published_at' => now()])->save();

        return $invitation;
    }

    public function test_only_admins_open_the_user_pages(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()->assertSeeLivewire(UserManager::class);
        $this->actingAs($this->admin)->get(route('admin.users.show', $client))->assertOk()->assertSeeLivewire(UserDetail::class)->assertSee($client->email);

        $this->actingAs($client)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($client)->get(route('admin.users.show', $this->admin))->assertForbidden();
    }

    public function test_the_list_searches_and_filters(): void
    {
        User::factory()->client()->create(['name' => 'Ana Lestari', 'email' => 'ana@example.test']);
        User::factory()->client()->create(['name' => 'Budi', 'email' => 'budi@example.test', 'status' => UserStatus::Suspended]);

        Livewire::actingAs($this->admin)->test(UserManager::class)
            ->assertSee('ana@example.test')
            ->assertSee('budi@example.test')
            ->set('search', 'lestari')
            ->assertSee('ana@example.test')
            ->assertDontSee('budi@example.test')
            ->set('search', '')
            ->set('status', 'suspended')
            ->assertSee('budi@example.test')
            ->assertDontSee('ana@example.test')
            ->set('status', '')
            ->set('role', 'super_admin')
            ->assertSee($this->admin->email)
            ->assertDontSee('ana@example.test');
    }

    public function test_the_user_page_shows_plan_and_invitations(): void
    {
        $client = User::factory()->client()->create();
        $invitation = $this->createInvitation($client);
        $this->subscribe($client, ['max_invitations' => 3]);

        Livewire::actingAs($this->admin)->test(UserDetail::class, ['user' => $client])
            ->assertSee($invitation->title)
            ->assertSee('Suspend account');
    }

    public function test_suspending_signs_the_user_out_and_takes_invitations_offline(): void
    {
        $client = User::factory()->client()->create();
        $invitation = $this->published($client);

        Livewire::actingAs($this->admin)->test(UserDetail::class, ['user' => $client])
            ->set('reason', 'Spam')
            ->call('suspend')
            ->assertDispatched('notify', type: 'success');

        $client->refresh();
        $this->assertSame(UserStatus::Suspended, $client->status);
        $this->assertNotNull($client->suspended_at);
        $this->assertSame('Spam', AuditLog::query()->where('action', 'user.suspended')->sole()->metadata['reason']);

        $this->get(route('invitations.public', $invitation->slug))->assertForbidden();
        $this->actingAs($client)->get(route('dashboard'))->assertRedirect(route('login'));

        Livewire::actingAs($this->admin)->test(UserDetail::class, ['user' => $client])->call('activate');

        $this->assertTrue($client->fresh()->isActive());
        $this->get(route('invitations.public', $invitation->slug))->assertOk();
    }

    public function test_deleting_removes_invitations_and_ends_the_subscription(): void
    {
        $client = User::factory()->client()->create();
        $invitation = $this->published($client);
        $subscription = $this->subscribe($client);

        Livewire::actingAs($this->admin)->test(UserDetail::class, ['user' => $client])
            ->call('delete')
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted($client);
        $this->assertSoftDeleted($invitation);
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->fresh()->status);
        $this->get(route('invitations.public', $invitation->slug))->assertNotFound();
        $this->assertTrue(AuditLog::query()->where('action', 'user.deleted')->exists());
    }

    public function test_admins_cannot_suspend_or_delete_themselves_or_other_admins(): void
    {
        $otherAdmin = User::factory()->superAdmin()->create();

        Livewire::actingAs($this->admin)->test(UserDetail::class, ['user' => $this->admin])
            ->call('suspend')
            ->assertDispatched('notify', type: 'error');

        Livewire::actingAs($this->admin)->test(UserDetail::class, ['user' => $otherAdmin])
            ->call('delete')
            ->assertDispatched('notify', type: 'error');

        $this->assertTrue($this->admin->fresh()->isActive());
        $this->assertNotSoftDeleted($otherAdmin);
    }

    public function test_clients_cannot_call_the_actions(): void
    {
        $client = User::factory()->client()->create();
        $other = User::factory()->client()->create();

        Livewire::actingAs($client)->test(UserDetail::class, ['user' => $other])->assertForbidden();
    }
}
