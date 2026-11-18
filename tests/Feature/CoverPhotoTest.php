<?php

namespace Tests\Feature;

use App\Livewire\SectionSettings;
use App\Models\Invitation;
use App\Services\Invitation\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    /**
     * @return array<string, string>
     */
    private function layouts(): array
    {
        return ['classic' => 'centered', 'classic ' => 'framed', 'botanical-cream' => 'centered', 'mocha-glass' => 'framed'];
    }

    public function test_every_design_shows_the_cover_photo_in_its_shape_and_size(): void
    {
        foreach ($this->layouts() as $design => $variant) {
            $invitation = $this->publishedWithPhoto(trim($design), $variant, ['photo_shape' => 'arch', 'photo_width' => 70]);

            $html = $this->get(route('invitations.public', $invitation->slug))->assertOk()->getContent();

            $this->assertStringContainsString('style="--inv-cover-photo-width: 70;"', $html, "[{$design} {$variant}] has no cover photo at its width.");
            $this->assertSame(1, substr_count($html, 'class="inv-cover-photo'), "[{$design}] renders the photo more than once.");
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

        $odd = $this->publishedWithPhoto('classic', config: [
            'photo_shape' => 'star', 'photo_width' => 999, 'photo_tilt' => -300, 'photo_border_width' => 4, 'photo_border_color' => 'red;}body{display:none',
            'photo_shadow' => 'glow', 'photo_placement' => 'floating', 'photo_layer' => 'top',
        ]);
        $this->get(route('invitations.public', $odd->slug))->assertOk()
            ->assertSee('style="--inv-cover-photo-width: 90; rotate: -45deg; --inv-cover-photo-border: 4px solid #ffffff;"', false)
            ->assertSee('inv-photo--circle', false)
            ->assertDontSee('inv-cover-photo--shadow', false)
            ->assertDontSee('data-inv-cover-photo-free', false)
            ->assertDontSee('display:none', false);
    }

    public function test_a_free_photo_sits_on_the_cover_itself_in_every_design(): void
    {
        foreach ($this->layouts() as $design => $variant) {
            $invitation = $this->publishedWithPhoto(trim($design), $variant, [
                'photo_placement' => 'free', 'photo_x' => 72.5, 'photo_y' => 18, 'photo_layer' => 'front',
                'photo_tilt' => 8, 'photo_border_width' => 3, 'photo_border_color' => '#B49A7C', 'photo_shadow' => 'soft',
            ]);

            $html = $this->get(route('invitations.public', $invitation->slug))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'class="inv-cover-photo'), "[{$design}] renders the photo more than once.");
            $this->assertStringContainsString('inv-cover-photo--free inv-cover-photo--front inv-cover-photo--shadow-soft', $html, "[{$design} {$variant}] ignores the free placement.");
            $this->assertMatchesRegularExpression('#style="--inv-cover-photo-width: 50; rotate: 8deg; --inv-cover-photo-border: 3px solid \#B49A7C; left: 72\.5%; top: 18%;"\s+data-inv-cover-photo-free\b#', $html, "[{$design}] misplaces the free photo.");
            // The editors drag the photo of the cover section whose settings are open.
            $coverUlid = $invitation->sections()->where('type', 'cover')->value('ulid');
            $this->assertStringContainsString('data-inv-cover-photo-free="'.$coverUlid.'"', $html, "[{$design}] does not name its section for dragging.");
        }
    }

    public function test_dragging_the_photo_in_the_preview_saves_its_place(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'closing']);
        $cover = $invitation->sections()->where('type', 'cover')->first();
        $settings = fn (string $type = 'cover') => Livewire::actingAs($invitation->user)->test(SectionSettings::class, [
            'invitationUlid' => $invitation->ulid,
            'sectionUlid' => $invitation->sections()->where('type', $type)->value('ulid'),
        ]);

        $settings()->assertSee('data-cover-photo-editor="'.$cover->ulid.'"', false)->call('placeCoverPhoto', 140.26, 33.33)->assertHasNoErrors();

        $config = $cover->fresh()->configuration;
        $this->assertSame('free', $config['photo_placement']);
        $this->assertEquals([110, 33.3], [$config['photo_x'], $config['photo_y']]);

        // Other sections have no cover photo: the call does nothing there.
        $settings('closing')->assertDontSee('data-cover-photo-editor', false)->call('placeCoverPhoto', 10, 10);
        $this->assertArrayNotHasKey('photo_x', $invitation->sections()->where('type', 'closing')->first()->configuration ?? []);
    }

    public function test_the_border_color_must_be_a_hex_color(): void
    {
        $invitation = $this->createInvitation(sections: ['cover']);

        Livewire::actingAs($invitation->user)->test(SectionSettings::class, [
            'invitationUlid' => $invitation->ulid,
            'sectionUlid' => $invitation->sections()->where('type', 'cover')->value('ulid'),
        ])
            ->assertSee('Photo border color')
            ->set('config.photo_border_color', 'red')
            ->assertHasErrors('config.photo_border_color')
            ->set('config.photo_border_color', '#AE543D')
            ->assertHasNoErrors();

        $this->assertSame('#AE543D', $invitation->sections()->where('type', 'cover')->first()->configuration['photo_border_color']);
    }
}
