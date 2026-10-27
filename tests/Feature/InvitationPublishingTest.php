<?php

namespace Tests\Feature;

use App\Events\InvitationPublished;
use App\Livewire\InvitationEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class InvitationPublishingTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    public function test_publishing_makes_the_invitation_public_and_is_audited(): void
    {
        $invitation = $this->createInvitation();

        $this->get('/invitation/'.$invitation->slug)->assertNotFound();

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->call('publish')
            ->assertSet('status', 'published');

        $this->get('/invitation/'.$invitation->slug)->assertOk();
        $this->assertNotNull($invitation->fresh()->published_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.published', 'auditable_id' => $invitation->id]);
    }

    public function test_publish_dispatches_event(): void
    {
        Event::fake([InvitationPublished::class]);
        $invitation = $this->createInvitation();

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->call('publish');

        Event::assertDispatched(InvitationPublished::class, fn (InvitationPublished $event): bool => $event->invitation->is($invitation));
    }

    public function test_unpublishing_hides_the_invitation_again(): void
    {
        $invitation = $this->createInvitation();

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->call('publish')
            ->call('unpublish')
            ->assertSet('status', 'draft');

        $this->get('/invitation/'.$invitation->slug)->assertNotFound();
    }

    public function test_invitation_without_enabled_sections_cannot_be_published(): void
    {
        $invitation = $this->createInvitation();
        $invitation->sections()->update(['is_enabled' => false]);

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->call('publish')
            ->assertHasErrors('publish')
            ->assertSet('status', 'draft');
    }
}
