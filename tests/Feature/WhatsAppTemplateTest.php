<?php

namespace Tests\Feature;

use App\Livewire\WhatsAppCampaignForm;
use App\Livewire\WhatsAppTemplateManager;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class WhatsAppTemplateTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    public function test_client_can_create_edit_and_delete_templates(): void
    {
        $user = User::factory()->client()->create();

        Livewire::actingAs($user)
            ->test(WhatsAppTemplateManager::class)
            ->call('create')
            ->assertSet('body', fn (string $body): bool => str_contains($body, '{{guest_name}}'))
            ->set('name', 'Main invitation')
            ->set('body', 'Hello {{guest_name}}, {{invitation_url}}')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Main invitation');

        $template = WhatsAppTemplate::query()->sole();
        $this->assertTrue($template->user->is($user));

        Livewire::actingAs($user)
            ->test(WhatsAppTemplateManager::class)
            ->call('edit', $template->ulid)
            ->set('body', 'Updated {{venue}}')
            ->call('save')
            ->call('delete', $template->ulid);

        $this->assertModelMissing($template);
    }

    public function test_unknown_variables_are_rejected(): void
    {
        Livewire::actingAs(User::factory()->client()->create())
            ->test(WhatsAppTemplateManager::class)
            ->call('create')
            ->set('name', 'Typo')
            ->set('body', 'Hi {{gues_name}}')
            ->call('save')
            ->assertHasErrors('body');

        $this->assertSame(0, WhatsAppTemplate::query()->count());
    }

    public function test_templates_of_other_users_are_off_limits(): void
    {
        $owner = User::factory()->client()->create();
        $template = new WhatsAppTemplate(['name' => 'Private', 'body' => 'Secret text']);
        $template->user()->associate($owner)->save();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(WhatsAppTemplateManager::class)
            ->assertDontSee('Private')
            ->call('delete', $template->ulid)
            ->assertForbidden();

        $this->assertModelExists($template);
    }

    public function test_choosing_a_template_fills_the_campaign_message(): void
    {
        $invitation = $this->createInvitation();
        $template = new WhatsAppTemplate(['name' => 'Reminder', 'body' => 'Reminder for {{guest_name}}']);
        $template->user()->associate($invitation->user)->save();

        Livewire::actingAs($invitation->user)
            ->test(WhatsAppCampaignForm::class, ['invitationUlid' => $invitation->ulid])
            ->set('templateUlid', $template->ulid)
            ->assertSet('message', 'Reminder for {{guest_name}}');
    }
}
