<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Services\Invitation\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

/**
 * The cover's own photo above the names (cover "photo"), in every design.
 */
class CoverPhotoTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function publishedWithPhoto(string $design, string $variant = 'centered', array $config = []): Invitation
    {
        $invitation = $this->createInvitation(sections: ['cover', 'closing']);
        $invitation->settings()->update(['design' => $design]);
        $invitation->sections()->where('type', 'cover')->update([
            'variant' => $variant,
            'configuration' => json_encode([
                ...app(SectionRegistry::class)->defaults('cover'),
                'title' => 'Rina & Dimas',
                'photo' => '/storage/invitations/demo/cover/couple.webp',
                'photo_focus' => '50% 30%',
                ...$config,
            ]),
        ]);
        $invitation->forceFill(['status' => 'published', 'published_at' => now()])->save();

        return $invitation;
    }

    public function test_every_design_shows_the_cover_photo_in_its_shape_and_size(): void
    {
        foreach (['classic' => 'centered', 'classic ' => 'framed', 'botanical-cream' => 'centered', 'mocha-glass' => 'framed'] as $design => $variant) {
            $invitation = $this->publishedWithPhoto(trim($design), $variant, ['photo_shape' => 'arch', 'photo_size' => 'large']);

            $html = $this->get(route('invitations.public', $invitation->slug))->assertOk()->getContent();

            $this->assertStringContainsString('inv-cover-photo inv-cover-photo--large', $html, "[{$design} {$variant}] has no cover photo.");
            $this->assertStringContainsString('inv-photo--arch', $html, "[{$design}] ignores the photo shape.");
            $this->assertMatchesRegularExpression('#<img src="/storage/invitations/demo/cover/couple\.webp" alt="Rina &amp; Dimas" loading="eager"[^>]*object-position: 50% 30%#', $html, "[{$design}] does not load the cover photo eagerly with its focus point.");
        }
    }

    public function test_a_frame_replaces_the_shape(): void
    {
        $invitation = $this->publishedWithPhoto('classic', config: ['photo_frame' => '/illustrations/frame-floral.svg', 'photo_frame_inset' => 18]);

        $this->get(route('invitations.public', $invitation->slug))
            ->assertOk()
            ->assertSee('inv-photo--framed', false)
            ->assertSee('--inv-photo-inset: 18%', false)
            ->assertSee('src="/illustrations/frame-floral.svg"', false);
    }

    public function test_no_photo_means_no_photo_block_and_unknown_options_fall_back(): void
    {
        $empty = $this->publishedWithPhoto('classic', config: ['photo' => null]);
        $this->get(route('invitations.public', $empty->slug))->assertOk()->assertDontSee('inv-cover-photo', false);

        // Stored values that never went through the editor form (e.g. an imported template) are cleaned on render.
        $unsafe = $this->publishedWithPhoto('classic', config: ['photo' => 'javascript:alert(1)']);
        $this->get(route('invitations.public', $unsafe->slug))->assertOk()->assertDontSee('inv-cover-photo', false)->assertDontSee('javascript:alert', false);

        $odd = $this->publishedWithPhoto('classic', config: ['photo_shape' => 'star', 'photo_size' => 'huge']);
        $this->get(route('invitations.public', $odd->slug))->assertOk()
            ->assertSee('inv-cover-photo inv-cover-photo--medium', false)
            ->assertSee('inv-photo--circle', false);
    }
}
