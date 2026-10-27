<?php

namespace Tests\Feature;

use App\Enums\GuestInvitationStatus;
use App\Enums\IllustrationCategory;
use App\Enums\InvitationStatus;
use App\Livewire\GuestImport;
use App\Livewire\GuestTable;
use App\Livewire\InvitationCreator;
use App\Livewire\InvitationEditor;
use App\Livewire\SectionSettings;
use App\Models\Guest;
use App\Models\Illustration;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

/**
 * Every place a plan limit must hold (rules.md section 48).
 */
class PlanEnforcementTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freePlan(['max_invitations' => 1, 'max_guests' => 2, 'max_whatsapp_messages' => 0, 'premium_templates' => false, 'premium_illustrations' => false, 'remove_branding' => false]);
    }

    private function published(Invitation $invitation): Invitation
    {
        $invitation->forceFill(['status' => InvitationStatus::Published, 'published_at' => now()])->save();

        return $invitation;
    }

    public function test_creator_blocks_a_second_invitation_and_premium_templates(): void
    {
        $user = User::factory()->client()->create();
        $free = InvitationTemplate::factory()->create();
        $premium = InvitationTemplate::factory()->create(['is_premium' => true]);

        $creator = fn (InvitationTemplate $template) => Livewire::actingAs($user)
            ->test(InvitationCreator::class, ['preselectedTemplate' => $template->slug])
            ->set('title', 'My Party')
            ->call('create');

        $creator($premium)->assertHasErrors(['plan' => 'This template is only available on Premium.']);
        $creator($free)->assertHasNoErrors()->assertRedirect();
        $creator($free)->assertHasErrors(['plan' => 'You have reached the invitation limit of your plan.']);

        $this->assertSame(1, $user->invitations()->count());
    }

    public function test_guest_limit_holds_for_the_form_and_the_import(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create();

        $table = Livewire::actingAs($invitation->user)->test(GuestTable::class, ['invitationUlid' => $invitation->ulid]);
        $table->call('create')->set('form.name', 'Second')->call('save')->assertHasNoErrors();
        $table->call('create')->set('form.name', 'Third')->call('save')->assertHasErrors(['name' => 'You have reached your guest limit.']);

        $this->assertSame(2, $invitation->guests()->count());

        $invitation->guests()->first()->delete();

        $preview = Livewire::actingAs($invitation->user)
            ->test(GuestImport::class, ['invitationUlid' => $invitation->ulid])
            ->set('file', UploadedFile::fake()->createWithContent('g.csv', "Name\nAna\nBudi"))
            ->assertSee('Your plan allows 1 more guests in this invitation.')
            ->get('preview');

        $this->assertSame(1, $preview['valid_count']);
        $this->assertSame('Over your plan guest limit.', $preview['invalid'][0]['errors'][0]);
    }

    public function test_premium_illustrations_need_a_plan(): void
    {
        $invitation = $this->createInvitation();
        $art = Illustration::factory()->category(IllustrationCategory::Cover)->create(['is_premium' => true, 'path' => 'illustrations/cover-botanical.svg']);
        $settings = fn () => Livewire::actingAs($invitation->user)->test(SectionSettings::class, [
            'invitationUlid' => $invitation->ulid,
            'sectionUlid' => $invitation->sections()->where('type', 'cover')->value('ulid'),
        ]);

        $settings()->call('openPicker', 'background_image')->call('pickIllustration', $art->ulid)
            ->assertHasErrors(['picker' => 'This illustration is only available on Premium.']);

        $this->subscribe($invitation->user, ['premium_illustrations' => true, 'max_invitations' => 1]);

        $settings()->call('openPicker', 'background_image')->call('pickIllustration', $art->ulid)->assertHasNoErrors();
        $this->assertSame('/illustrations/cover-botanical.svg', $invitation->sections()->where('type', 'cover')->first()->configuration['background_image']);
    }

    public function test_offline_invitations_are_closed_to_guests_without_revealing_billing(): void
    {
        $owner = User::factory()->client()->create();
        $this->published($this->createInvitation($owner, ['cover', 'rsvp']));
        $this->travel(1)->minutes();
        $offline = $this->published($this->createInvitation($owner, ['cover', 'rsvp']));
        $guest = Guest::factory()->for($offline)->create();

        $this->get('/invitation/'.$offline->slug)
            ->assertForbidden()
            ->assertSee('This invitation is currently unavailable.')
            ->assertDontSee('plan');

        $this->get('/invitation/'.$offline->slug.'/'.$guest->token)->assertForbidden();
        $this->assertSame(GuestInvitationStatus::NotSent, $guest->fresh()->invitation_status);

        $this->post('/invitation/'.$offline->slug.'/rsvp', ['name' => 'X', 'attendance' => 'attending', 'number_of_guests' => 1])->assertNotFound();

        $this->actingAs($owner)->get('/dashboard/invitations')->assertSee('Offline');
    }

    public function test_publishing_an_uncovered_invitation_is_refused(): void
    {
        $owner = User::factory()->client()->create();
        $this->createInvitation($owner);
        $this->travel(1)->minutes();
        $second = $this->createInvitation($owner);

        Livewire::actingAs($owner)
            ->test(InvitationEditor::class, ['invitation' => $second])
            ->call('publish')
            ->assertHasErrors('publish');

        $this->assertSame(InvitationStatus::Draft, $second->fresh()->status);
    }

    public function test_branding_is_removed_only_when_the_plan_allows_it(): void
    {
        $invitation = $this->published($this->createInvitation());

        $this->get('/invitation/'.$invitation->slug)->assertSee('Made with');

        $this->subscribe($invitation->user, ['remove_branding' => true, 'max_invitations' => 1]);

        $this->get('/invitation/'.$invitation->slug)->assertOk()->assertDontSee('Made with');
    }
}
