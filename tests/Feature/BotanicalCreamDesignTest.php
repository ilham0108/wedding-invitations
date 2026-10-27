<?php

namespace Tests\Feature;

use App\Models\GuestMessage;
use App\Models\InvitationTemplate;
use App\Services\Invitation\InvitationService;
use Database\Seeders\IllustrationSeeder;
use Database\Seeders\InvitationTemplateSeeder;
use Database\Seeders\TemplateCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class BotanicalCreamDesignTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function template(array $attributes = []): InvitationTemplate
    {
        return InvitationTemplate::factory()->create([
            'design' => 'botanical-cream',
            'schema' => ['sections' => ['cover', 'couple', 'event', 'story', 'gallery', 'wishes', 'rsvp', 'closing']],
            'sample_content' => [
                'cover' => ['title' => 'Ilham & Aisyah', 'subtitle' => 'The Wedding of', 'date_text' => 'Sabtu, 12 Desember 2026'],
                'event' => ['events' => [
                    ['title' => 'Akad Nikah', 'date' => '2026-12-12', 'start_time' => '08:00'],
                    ['title' => 'Resepsi', 'date' => '2026-12-12', 'start_time' => '11:00', 'add_to_calendar' => true],
                ]],
                'story' => [
                    'entries' => [['icon' => 'ring', 'title' => 'Lamaran', 'date' => '2025']],
                    'closing_quote' => 'Perjalanan ini bukan tentang siapa yang datang lebih dulu.',
                ],
                'gallery' => ['images' => array_map(fn (int $n): string => "/images/demo/gallery-{$n}.svg", [1, 2, 3, 4, 1, 2, 3])],
            ],
            ...$attributes,
        ]);
    }

    public function test_the_preview_renders_the_page_frame_monogram_and_menus(): void
    {
        $this->get(route('templates.preview', $this->template()))
            ->assertOk()
            ->assertSee('inv-design-botanical-cream', false)
            ->assertSee('data-bc-root', false)
            ->assertSee('great-vibes', false)
            // Monogram from the cover names, script names with the "&" set apart.
            ->assertSeeInOrder(['bc-monogram', '<span>I</span>', '<span>A</span>'], false)
            ->assertSee('bc-names__amp', false)
            ->assertSee('Sabtu, 12 Desember 2026')
            // Labelled bottom menu (five types at most) and the full menu with every section that has one.
            ->assertSeeInOrder(['class="bc-nav"', __('Home'), __('Couple'), __('Event'), __('Gallery'), __('Wishes')], false)
            ->assertSeeInOrder(['id="bc-menu"', __('Love Story'), __('RSVP')], false)
            // Footer with the first calendar link of the events.
            ->assertSee(__('See you on our happy day'))
            ->assertSee('calendar.google.com', false);
    }

    public function test_sections_use_the_design_views_and_other_sections_sit_in_a_cream_card(): void
    {
        $this->get(route('templates.preview', $this->template()))
            ->assertOk()
            ->assertSee('fa-solid fa-ring bc-event__icon', false)
            ->assertSee('fa-solid fa-champagne-glasses bc-event__icon', false)
            ->assertSee('bc-timeline__icon"><i class="fa-solid fa-ring"', false)
            ->assertSee('Perjalanan ini bukan tentang siapa yang datang lebih dulu.')
            ->assertSee(__('See all photos (:count)', ['count' => 7]))
            ->assertSee('bc-card bc-classic', false)
            ->assertSee('data-section="rsvp"', false);
    }

    public function test_wishes_are_paged_three_at_a_time(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'wishes']);
        $invitation->settings()->update(['design' => 'botanical-cream']);
        $invitation->forceFill(['status' => 'published', 'published_at' => now()])->save();

        foreach (range(1, 4) as $n) {
            $message = new GuestMessage(['name' => "Tamu {$n}", 'message' => "Selamat {$n}", 'status' => 'approved']);
            $message->invitation()->associate($invitation);
            $message->save();
        }

        $html = $this->get(route('invitations.public', $invitation->slug))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="bc-wishes__page"'));
        $this->assertStringContainsString('bc-wish__avatar', $html);
        $this->assertStringContainsString(__('Page :n', ['n' => 2]), $html);
    }

    public function test_the_seeded_template_is_valid_and_creates_invitations(): void
    {
        $this->seed([TemplateCategorySeeder::class, IllustrationSeeder::class, InvitationTemplateSeeder::class]);

        $template = InvitationTemplate::query()->where('slug', 'botanical-cream')->firstOrFail();
        $this->assertSame('botanical-cream', $template->design);

        $this->get(route('templates.preview', $template))->assertOk()->assertSee('Ilham')->assertSee('Kisah Cinta');

        $invitation = app(InvitationService::class)->createFromTemplate($this->createInvitation()->user, $template, [
            'title' => 'Ilham & Aisyah', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);
        $this->assertSame('botanical-cream', $invitation->settings->design);
    }
}
