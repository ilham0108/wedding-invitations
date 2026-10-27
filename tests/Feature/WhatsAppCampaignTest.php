<?php

namespace Tests\Feature;

use App\Enums\GuestInvitationStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Jobs\ProcessWhatsAppCampaign;
use App\Livewire\GuestTable;
use App\Livewire\WhatsAppCampaignDetail;
use App\Livewire\WhatsAppCampaignForm;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

class WhatsAppCampaignTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        WhatsAppProvider::factory()->create();
    }

    /**
     * @param  array<int, string>  $preselected
     */
    private function form(Invitation $invitation, array $preselected = []): Testable
    {
        return Livewire::actingAs($invitation->user)->test(WhatsAppCampaignForm::class, [
            'invitationUlid' => $invitation->ulid,
            'preselected' => $preselected,
        ]);
    }

    public function test_pages_render_for_the_owner(): void
    {
        $invitation = $this->createInvitation();

        $this->actingAs($invitation->user)->get('/dashboard/whatsapp')->assertOk()->assertSee('Campaigns');
        $this->actingAs($invitation->user)->get('/dashboard/whatsapp/create?invitation='.$invitation->ulid)
            ->assertOk()->assertSeeLivewire(WhatsAppCampaignForm::class);
        $this->actingAs($invitation->user)->get('/dashboard/whatsapp/templates')->assertOk();
    }

    public function test_sending_now_creates_recipients_for_guests_with_a_phone_and_queues_the_campaign(): void
    {
        $invitation = $this->createInvitation();
        $robert = Guest::factory()->for($invitation)->create(['name' => 'Robert', 'phone' => '6281111111111']);
        Guest::factory()->for($invitation)->create(['name' => 'No Phone', 'phone' => null]);

        $this->form($invitation)
            ->assertSee('1 guest will receive this message.')
            ->assertSee('1 without a WhatsApp number will be skipped.')
            ->set('message', 'Hi {{guest_name}}: {{invitation_url}}')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();

        $campaign = WhatsAppCampaign::query()->sole();
        $recipient = $campaign->recipients()->sole();

        $this->assertSame(WhatsAppCampaignStatus::Processing, $campaign->status);
        $this->assertSame(1, $campaign->total_recipients);
        $this->assertSame('6281111111111', $recipient->phone);
        $this->assertSame('Hi Robert: '.route('invitations.personal', ['slug' => $invitation->slug, 'token' => $robert->token]), $recipient->message);
        Queue::assertPushed(ProcessWhatsAppCampaign::class, fn (ProcessWhatsAppCampaign $job): bool => $job->campaignUlid === $campaign->ulid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'whatsapp.campaign_created', 'auditable_id' => $campaign->id]);
    }

    public function test_filter_audience_only_reaches_matching_guests(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create(['name' => 'Already Sent', 'invitation_status' => GuestInvitationStatus::Sent]);
        Guest::factory()->for($invitation)->create(['name' => 'Fresh Guest']);

        $this->form($invitation)
            ->set('audience', 'filter')
            ->set('filters.status', 'not_sent')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(['Fresh Guest'], WhatsAppCampaign::query()->sole()->recipients()->pluck('name')->all());
    }

    public function test_guests_selected_in_the_guest_list_become_the_audience(): void
    {
        $invitation = $this->createInvitation();
        $chosen = Guest::factory()->for($invitation)->create(['name' => 'Chosen']);
        Guest::factory()->for($invitation)->create(['name' => 'Not Chosen']);
        $foreign = Guest::factory()->for($this->createInvitation())->create(['name' => 'Foreign']);

        Livewire::actingAs($invitation->user)
            ->test(GuestTable::class, ['invitationUlid' => $invitation->ulid])
            ->set('selected', [$chosen->ulid, $foreign->ulid])
            ->call('sendWhatsApp')
            ->assertRedirect(route('dashboard.whatsapp.create', ['invitation' => $invitation->ulid]));

        $this->assertSame([$chosen->ulid], session('whatsapp.selected_guests.'.$invitation->ulid));

        // Even a forged preselection cannot pull in another invitation's guest.
        $this->form($invitation, [$chosen->ulid, $foreign->ulid])
            ->assertSet('audience', 'selected')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(['Chosen'], WhatsAppCampaign::query()->sole()->recipients()->pluck('name')->all());
    }

    public function test_scheduling_converts_the_local_time_to_utc(): void
    {
        $this->travelTo(now('UTC')->setDate(2030, 1, 1)->setTime(0, 0));
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create();

        $this->form($invitation)
            ->set('schedule', true)
            ->set('scheduledAt', '2030-01-02T09:00')
            ->set('timezone', 'Asia/Jakarta')
            ->call('submit')
            ->assertHasNoErrors();

        $campaign = WhatsAppCampaign::query()->sole();
        $this->assertSame(WhatsAppCampaignStatus::Scheduled, $campaign->status);
        $this->assertSame('2030-01-02 02:00:00', $campaign->scheduled_at->utc()->format('Y-m-d H:i:s'));
        Queue::assertNotPushed(ProcessWhatsAppCampaign::class);

        $this->artisan('whatsapp:dispatch-scheduled')->assertSuccessful();
        Queue::assertNotPushed(ProcessWhatsAppCampaign::class);

        $this->travelTo(now('UTC')->setDate(2030, 1, 2)->setTime(2, 0));
        $this->artisan('whatsapp:dispatch-scheduled')->expectsOutputToContain('Queued 1 scheduled campaign(s).');

        $this->assertSame(WhatsAppCampaignStatus::Processing, $campaign->fresh()->status);
        Queue::assertPushed(ProcessWhatsAppCampaign::class);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create();

        $this->form($invitation)
            ->set('message', 'Hi {{guest_nmae}}')
            ->call('submit')
            ->assertHasErrors('message')
            ->set('message', 'Hi {{guest_name}}')
            ->set('schedule', true)
            ->set('scheduledAt', now()->subDay()->format('Y-m-d\TH:i'))
            ->call('submit')
            ->assertHasErrors('scheduled_at')
            ->set('timezone', 'Mars/Olympus')
            ->call('submit')
            ->assertHasErrors('timezone');

        $this->assertSame(0, WhatsAppCampaign::query()->count());
    }

    public function test_campaign_needs_reachable_guests_and_a_provider(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create(['phone' => null]);

        $this->form($invitation)->call('submit')->assertHasErrors('audience');

        WhatsAppProvider::query()->update(['is_active' => false]);
        Guest::factory()->for($invitation)->create();

        $this->form($invitation)
            ->assertSee('WhatsApp provider is not configured.')
            ->call('submit')
            ->assertHasErrors(['sender' => 'WhatsApp provider is not configured.']);

        $this->assertSame(0, WhatsAppCampaign::query()->count());
    }

    public function test_monthly_limit_blocks_campaigns_that_do_not_fit(): void
    {
        $invitation = $this->createInvitation();
        $this->subscribe($invitation->user, ['max_whatsapp_messages' => 2]);
        Guest::factory()->count(3)->for($invitation)->create();

        $this->form($invitation)
            ->assertSee('2 messages left this month.')
            ->call('submit')
            ->assertHasErrors('audience');

        $this->assertSame(0, WhatsAppCampaign::query()->count());
    }

    public function test_other_users_cannot_view_or_cancel_a_campaign(): void
    {
        $invitation = $this->createInvitation();
        $campaign = WhatsAppCampaign::factory()->for($invitation)->for($invitation->user)->create(['status' => WhatsAppCampaignStatus::Scheduled]);
        $intruder = User::factory()->client()->create();

        $this->actingAs($intruder)->get('/dashboard/whatsapp/campaigns/'.$campaign->ulid)->assertForbidden();

        Livewire::actingAs($intruder)
            ->test(WhatsAppCampaignDetail::class, ['campaignUlid' => $campaign->ulid])
            ->assertForbidden();

        $this->actingAs($intruder)->get('/dashboard/whatsapp/create?invitation='.$invitation->ulid)->assertNotFound();
        $this->assertSame(WhatsAppCampaignStatus::Scheduled, $campaign->fresh()->status);
    }

    public function test_owner_can_send_a_scheduled_campaign_now_or_cancel_it(): void
    {
        $invitation = $this->createInvitation();
        $campaign = WhatsAppCampaign::factory()->for($invitation)->for($invitation->user)->create(['status' => WhatsAppCampaignStatus::Scheduled, 'scheduled_at' => now()->addDay()]);

        Livewire::actingAs($invitation->user)
            ->test(WhatsAppCampaignDetail::class, ['campaignUlid' => $campaign->ulid])
            ->assertSee('Scheduled for')
            ->call('sendNow');

        $this->assertSame(WhatsAppCampaignStatus::Processing, $campaign->fresh()->status);

        Livewire::actingAs($invitation->user)
            ->test(WhatsAppCampaignDetail::class, ['campaignUlid' => $campaign->ulid])
            ->call('cancel');

        $this->assertSame(WhatsAppCampaignStatus::Cancelled, $campaign->fresh()->status);
    }
}
