<?php

namespace Tests\Feature;

use App\Livewire\SectionManager;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class SectionManagerTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function manager(Invitation $invitation): Testable
    {
        return Livewire::actingAs($invitation->user)->test(SectionManager::class, ['invitationUlid' => $invitation->ulid]);
    }

    /**
     * @return array<int, string>
     */
    private function order(Invitation $invitation): array
    {
        return $invitation->sections()->pluck('type')->all();
    }

    public function test_sections_can_be_reordered_by_drag_and_drop(): void
    {
        $invitation = $this->createInvitation();
        $closing = $invitation->sections()->where('type', 'closing')->value('ulid');

        $this->manager($invitation)->call('reorder', $closing, 0)->assertDispatched('invitation-updated');

        $this->assertSame(['closing', 'cover', 'couple', 'gallery'], $this->order($invitation));
        $this->assertSame([0, 1, 2, 3], $invitation->sections()->pluck('sort_order')->all());
    }

    public function test_sections_can_be_added_toggled_duplicated_and_removed(): void
    {
        $invitation = $this->createInvitation();
        $couple = $invitation->sections()->where('type', 'couple')->value('ulid');

        $this->manager($invitation)
            ->call('add', 'video')
            ->assertDispatched('section-selected')
            ->call('toggle', $couple)
            ->call('duplicate', $couple)
            ->call('add', 'not-a-section');

        $this->assertSame(['cover', 'couple', 'couple', 'gallery', 'closing', 'video'], $this->order($invitation));
        $this->assertFalse($invitation->sections()->where('ulid', $couple)->value('is_enabled'));

        $this->manager($invitation)->call('remove', $couple)->assertDispatched('section-removed');

        $this->assertSame(['cover', 'couple', 'gallery', 'closing', 'video'], $this->order($invitation));
    }

    public function test_sections_of_another_invitation_cannot_be_touched(): void
    {
        $invitation = $this->createInvitation();
        $foreign = $this->createInvitation();
        $foreignSection = $foreign->sections()->value('ulid');

        $this->manager($invitation)->call('remove', $foreignSection)->assertNotFound();

        $this->assertTrue($foreign->sections()->where('ulid', $foreignSection)->exists());
    }

    public function test_non_owner_cannot_mount_the_manager(): void
    {
        $invitation = $this->createInvitation();
        $intruder = $this->createInvitation()->user;

        Livewire::actingAs($intruder)
            ->test(SectionManager::class, ['invitationUlid' => $invitation->ulid])
            ->assertForbidden();
    }
}
