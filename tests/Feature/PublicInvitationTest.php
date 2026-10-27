<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function publishedInvitation(array $schema = ['cover', 'quote', 'event', 'countdown', 'closing']): Invitation
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => $schema]]);

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'John & Jane',
            'event_type' => 'wedding',
            'timezone' => 'Asia/Jakarta',
            'event_date' => '2030-06-12',
            'event_time' => '10:00',
            'venue' => 'Grand Ballroom',
        ]);

        $invitation->forceFill(['status' => InvitationStatus::Published, 'published_at' => now()])->save();

        return $invitation;
    }

    public function test_published_invitation_renders_sections_in_order(): void
    {
        $invitation = $this->publishedInvitation();

        $this->get('/invitation/'.$invitation->slug)
            ->assertOk()
            ->assertSeeInOrder(['data-section="cover"', 'data-section="quote"', 'data-section="event"', 'data-section="countdown"', 'data-section="closing"'], false)
            ->assertSee('John &amp; Jane', false)
            ->assertSee('Grand Ballroom')
            ->assertSee('2030-06-12T10:00:00+07:00', false);
    }

    public function test_disabled_sections_are_not_rendered(): void
    {
        $invitation = $this->publishedInvitation();
        $invitation->sections()->where('type', 'quote')->update(['is_enabled' => false]);

        $this->get('/invitation/'.$invitation->slug)
            ->assertOk()
            ->assertDontSee('data-section="quote"', false);
    }

    public function test_draft_and_unknown_invitations_return_not_found(): void
    {
        $draft = Invitation::factory()->create();

        $this->get('/invitation/'.$draft->slug)->assertNotFound();
        $this->get('/invitation/does-not-exist')->assertNotFound();
    }

    public function test_expired_invitation_returns_gone(): void
    {
        $expired = Invitation::factory()->expired()->create();

        $this->get('/invitation/'.$expired->slug)
            ->assertStatus(410)
            ->assertSee('This invitation has expired.');
    }

    public function test_invitation_is_not_indexable_by_default_but_can_opt_in(): void
    {
        $invitation = $this->publishedInvitation();

        $this->get('/invitation/'.$invitation->slug)->assertSee('noindex, nofollow', false);

        $invitation->settings->update(['allow_search_indexing' => true]);

        $this->get('/invitation/'.$invitation->slug)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('invitations.public', $invitation->slug).'">', false);
    }

    public function test_user_content_is_escaped_and_unsafe_urls_are_dropped(): void
    {
        $invitation = $this->publishedInvitation(['cover', 'maps']);

        $invitation->sections()->where('type', 'cover')->first()->update(['configuration' => [
            'title' => '<script>alert(1)</script>',
            'background_image' => 'javascript:alert(1)',
        ]]);
        $invitation->sections()->where('type', 'maps')->first()->update(['configuration' => [
            'maps_url' => 'javascript:alert(2)',
        ]]);

        $this->get('/invitation/'.$invitation->slug)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('javascript:alert', false);
    }

    public function test_malicious_theme_values_never_reach_the_style_block(): void
    {
        $invitation = $this->publishedInvitation(['cover']);
        $invitation->settings->update(['theme' => ['primary_color' => '#fff;}</style><script>alert(1)</script>']]);

        $this->get('/invitation/'.$invitation->slug)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('--inv-primary: '.config('invitation.theme_presets.elegant.primary_color').';', false);
    }
}
