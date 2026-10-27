<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Livewire\InvitationCreator;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\TemplateCategory;
use App\Models\User;
use App\Services\Invitation\InvitationRenderer;
use App\Services\Invitation\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationCreationTest extends TestCase
{
    use RefreshDatabase;

    private function weddingTemplate(): InvitationTemplate
    {
        return InvitationTemplate::factory()
            ->for(TemplateCategory::factory()->state(['slug' => 'wedding', 'name' => 'Wedding']), 'category')
            ->create([
                'slug' => 'elegant',
                'schema' => ['sections' => [['type' => 'cover', 'variant' => 'framed'], 'couple', ['type' => 'event', 'background' => 'muted'], 'unknown-type', 'closing']],
                'default_configuration' => ['couple' => ['heading' => 'The Bride & Groom']],
            ]);
    }

    public function test_create_page_renders_the_wizard(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get('/dashboard/invitations/create')
            ->assertOk()
            ->assertSeeLivewire(InvitationCreator::class);
    }

    public function test_wizard_creates_a_draft_invitation_from_the_template(): void
    {
        $user = User::factory()->client()->create();
        $this->weddingTemplate();

        Livewire::actingAs($user)
            ->test(InvitationCreator::class)
            ->call('selectEventType', 'wedding')
            ->assertSet('step', 2)
            ->assertSee('Use Template')
            ->call('selectTemplate', 'elegant')
            ->assertSet('step', 3)
            ->set('title', 'John & Jane')
            ->set('eventDate', '2030-06-12')
            ->set('eventTime', '10:00')
            ->set('venue', 'Grand Ballroom')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect();

        $invitation = Invitation::query()->with('sections')->sole();

        $this->assertTrue($invitation->user->is($user));
        $this->assertSame(InvitationStatus::Draft, $invitation->status);
        $this->assertSame('john-jane', $invitation->slug);
        $this->assertSame(['cover', 'couple', 'event', 'closing'], $invitation->sections->pluck('type')->all());
        $this->assertSame('framed', $invitation->sections[0]->variant);
        $this->assertSame('John & Jane', $invitation->sections[0]->configuration['title']);
        $this->assertSame('The Bride & Groom', $invitation->sections[1]->configuration['heading']);
        $this->assertSame('muted', $invitation->sections[2]->theme['background']);
        $this->assertSame('Grand Ballroom', $invitation->sections[2]->configuration['events'][0]['venue']);
        $this->assertNotNull($invitation->settings);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.created', 'auditable_id' => $invitation->id]);
    }

    public function test_preselected_template_skips_to_details_step(): void
    {
        $this->weddingTemplate();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(InvitationCreator::class, ['preselectedTemplate' => 'elegant'])
            ->assertSet('step', 3)
            ->assertSet('eventType', 'wedding');
    }

    public function test_wizard_validates_input(): void
    {
        $this->weddingTemplate();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(InvitationCreator::class, ['preselectedTemplate' => 'elegant'])
            ->set('title', '')
            ->set('eventTime', '25:99')
            ->call('create')
            ->assertHasErrors(['title', 'event_time']);

        $this->assertSame(0, Invitation::query()->count());
    }

    public function test_draft_templates_cannot_be_used(): void
    {
        InvitationTemplate::factory()->draft()->create(['slug' => 'secret']);

        Livewire::actingAs(User::factory()->client()->create())
            ->test(InvitationCreator::class)
            ->call('selectEventType', 'wedding')
            ->call('selectTemplate', 'secret')
            ->assertSet('step', 2)
            ->set('templateSlug', 'secret')
            ->set('title', 'Sneaky')
            ->call('create')
            ->assertHasErrors(['template']);

        $this->assertSame(0, Invitation::query()->count());
    }

    public function test_slugs_stay_unique_including_soft_deleted_invitations(): void
    {
        $service = app(InvitationService::class);
        $template = $this->weddingTemplate();
        $user = User::factory()->client()->create();
        $data = ['title' => 'John & Jane', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta'];

        $first = $service->createFromTemplate($user, $template, $data);
        $first->delete();
        $second = $service->createFromTemplate($user, $template, $data);

        $this->assertSame('john-jane', $first->slug);
        $this->assertNotSame($first->slug, $second->slug);
        $this->assertStringStartsWith('john-jane-', $second->slug);
    }

    public function test_later_template_edits_do_not_change_existing_invitations(): void
    {
        $template = $this->weddingTemplate();
        $template->update([
            'theme' => ['preset' => 'elegant', 'primary_color' => '#112233'],
            'decoration' => ['divider' => 'flourish'],
            'default_configuration' => ['couple' => ['heading' => 'The Bride & Groom']],
        ]);

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'John & Jane', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $template->update([
            'theme' => ['preset' => 'luxury', 'primary_color' => '#aa0000'],
            'decoration' => ['divider' => 'dots'],
            'default_configuration' => ['couple' => ['heading' => 'Changed Later']],
        ]);

        $rendered = app(InvitationRenderer::class)->forInvitation($invitation->fresh());

        $this->assertSame(['preset' => 'elegant', 'primary_color' => '#112233'], $invitation->settings->template_theme);
        $this->assertSame('#112233', $rendered->theme['primary_color']);
        $this->assertSame('Cormorant Garamond', $rendered->theme['font_heading']);
        $this->assertSame('flourish', $rendered->decoration['divider']);
        $this->assertSame('The Bride & Groom', collect($rendered->sections)->firstWhere('type', 'couple')['config']['heading']);
    }

    public function test_client_theme_changes_still_override_the_template_copy(): void
    {
        $template = $this->weddingTemplate();
        $template->update(['theme' => ['preset' => 'elegant', 'primary_color' => '#112233']]);
        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'John & Jane', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        app(InvitationService::class)->updateTheme($invitation, ['primary_color' => '#445566']);

        $rendered = app(InvitationRenderer::class)->forInvitation($invitation->fresh());

        $this->assertSame('#445566', $rendered->theme['primary_color']);
        $this->assertSame('Cormorant Garamond', $rendered->theme['font_heading']);
    }
}
