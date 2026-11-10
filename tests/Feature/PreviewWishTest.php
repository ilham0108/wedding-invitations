<?php

namespace Tests\Feature;

use App\Enums\TemplateStatus;
use App\Models\GuestMessage;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationRenderer;
use App\Services\Invitation\InvitationService;
use App\Services\Rsvp\PreviewGuestbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Wishes sent from a preview are kept in the cache for the sender only (PreviewGuestbook), never in the guestbook.
 */
class PreviewWishTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_template_preview_wish_shows_for_the_sender_only(): void
    {
        $template = $this->template();
        $preview = route('templates.preview', $template);

        $this->visitor('a')->get($preview)->assertOk()
            ->assertSee('action="'.route('templates.preview.wishes', $template).'"', false)
            ->assertSee('Preview: only you see the wishes you send here, for 2 hours. They never reach the hosts.');

        $this->visitor('a')->from($preview)->post(route('templates.preview.wishes', $template), ['name' => 'Rina', 'message' => '<b>Selamat</b> menempuh hidup baru'])
            ->assertRedirect($preview.'#wishes-form');

        $this->visitor('a')->get($preview)->assertOk()->assertSee('Rina')->assertSee('&lt;b&gt;Selamat&lt;/b&gt;', false);
        $this->assertSame(0, GuestMessage::query()->count());

        // Another visitor (another session) sees nothing of it.
        $this->visitor('b')->get($preview)->assertOk()->assertDontSee('Selamat menempuh');
    }

    public function test_a_visitor_keeps_at_most_the_latest_wishes(): void
    {
        $template = $this->template();

        for ($i = 1; $i <= PreviewGuestbook::MAX_MESSAGES + 2; $i++) {
            $this->visitor('a')->withoutMiddleware(ThrottleRequests::class)
                ->post(route('templates.preview.wishes', $template), ['name' => 'Tamu', 'message' => "Ucapan nomor {$i}x"]);
        }

        $html = $this->visitor('a')->get(route('templates.preview', $template))->getContent();

        $this->assertSame(PreviewGuestbook::MAX_MESSAGES, substr_count($html, 'Ucapan nomor'));
        $this->assertStringNotContainsString('Ucapan nomor 1x', $html);
        $this->assertStringContainsString('Ucapan nomor 12x', $html);
    }

    public function test_the_form_keeps_its_validation_and_rate_limit(): void
    {
        $template = $this->template();
        $url = route('templates.preview.wishes', $template);

        $this->post($url, ['name' => 'Rina', 'message' => str_repeat('a', 501)])->assertSessionHasErrors('message', null, 'wishes');
        $this->post($url, ['name' => 'Bot', 'message' => 'Halo', 'website' => 'spam'])->assertSessionHasErrors('website', null, 'wishes');

        for ($i = 0; $i < 3; $i++) {
            $this->post($url, ['name' => 'Rina', 'message' => 'Halo']);
        }

        $this->post($url, ['name' => 'Rina', 'message' => 'Halo'])->assertStatus(429);
    }

    public function test_draft_templates_take_wishes_from_template_admins_only(): void
    {
        $template = $this->template(['status' => TemplateStatus::Draft]);

        $this->post(route('templates.preview.wishes', $template), ['name' => 'Rina', 'message' => 'Halo'])->assertNotFound();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('templates.preview.wishes', $template), ['name' => 'Rina', 'message' => 'Halo'])
            ->assertRedirect();
    }

    public function test_the_owner_preview_keeps_wishes_apart_from_the_guestbook(): void
    {
        $owner = User::factory()->client()->create();
        $invitation = app(InvitationService::class)->createFromTemplate($owner, $this->template(), [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);
        $preview = route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]);

        $this->actingAs(User::factory()->client()->create())
            ->post(route('dashboard.invitations.preview.wishes', $invitation), ['name' => 'Rina', 'message' => 'Halo'])
            ->assertForbidden();

        $this->visitor('a')->actingAs($owner)->from($preview)
            ->post(route('dashboard.invitations.preview.wishes', $invitation), ['name' => 'Pemilik', 'message' => 'Coba ucapan pratinjau'])
            ->assertRedirect();

        $this->visitor('a')->actingAs($owner)->get($preview)->assertOk()->assertSee('Coba ucapan pratinjau')->assertSee('Pemilik');
        $this->assertSame(0, $invitation->guestMessages()->count());
    }

    public function test_published_invitations_still_post_to_the_guestbook(): void
    {
        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $this->template(), [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->assertStringContainsString(route('invitations.wishes', $invitation->slug), (string) $this->renderPublic($invitation));
    }

    private function renderPublic($invitation): string
    {
        $rendered = app(InvitationRenderer::class)->forInvitation($invitation);

        return $rendered->interaction['wishes']['action'];
    }

    /**
     * Requests of one browser: the test client sends no session cookie by itself, so every request would start a new session.
     */
    private function visitor(string $name): static
    {
        return $this->withCookie(config('session.cookie'), str_pad($name, 40, 'x'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function template(array $attributes = []): InvitationTemplate
    {
        return InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'wishes']], ...$attributes]);
    }
}
