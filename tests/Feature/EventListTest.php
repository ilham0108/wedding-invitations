<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use App\Services\Invitation\InvitationRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Event icons, readable times, and the classic "list" layout (rows with icons, one shared place).
 */
class EventListTest extends TestCase
{
    use RefreshDatabase;

    private const PLACE = ['venue' => 'The Heritage Ballroom', 'address' => 'Jl. Diponegoro No. 25, Yogyakarta', 'maps_url' => 'https://maps.google.com/?q=heritage'];

    private const EVENTS = [
        ['title' => 'Akad Nikah', 'date' => '2026-12-12', 'start_time' => '08:00', ...self::PLACE],
        ['title' => 'Resepsi', 'date' => '2026-12-12', 'start_time' => '11:00', 'end_time' => '14:00', ...self::PLACE],
    ];

    public function test_events_get_an_icon_a_time_label_and_a_shared_place(): void
    {
        $config = $this->render('classic', 'list', [...self::EVENTS, ['title' => 'Syukuran', 'icon' => 'meal', 'start_time' => '19:00', ...self::PLACE]]);

        $this->assertSame(['fa-solid fa-ring', 'fa-solid fa-champagne-glasses', 'fa-solid fa-utensils'], array_column($config['events'], 'icon_class'));
        $this->assertSame([false, false, true], array_column($config['events'], 'icon_chosen'));
        $this->assertSame('11:00 – 14:00 WIB', $config['events'][1]['time_label']);
        $this->assertSame(self::PLACE, $config['shared_place']);

        app()->setLocale('id');
        $this->assertSame('08.00 WIB', $this->render('classic', 'list', self::EVENTS)['events'][0]['time_label']);
    }

    public function test_events_in_different_places_have_no_shared_place(): void
    {
        $config = $this->render('classic', 'list', [self::EVENTS[0], [...self::EVENTS[1], 'venue' => 'Rumah Mempelai Pria']]);
        $this->assertNull($config['shared_place']);

        // A single event shows its place in its own row.
        $this->assertNull($this->render('classic', 'list', [self::EVENTS[0]])['shared_place']);
    }

    public function test_the_classic_list_shows_icons_and_the_shared_place_once(): void
    {
        $html = $this->page('classic', 'list', self::EVENTS);

        $this->assertSame(2, substr_count($html, '<article class="inv-event-row'), 'two events');
        $this->assertSame(3, substr_count($html, 'class="inv-event-row'), 'two events and the shared place');
        $this->assertSame(1, substr_count($html, 'The Heritage Ballroom'));
        $this->assertSame(1, substr_count($html, 'https://maps.google.com/?q=heritage'));
        $this->assertStringContainsString('fa-solid fa-ring inv-event-row__icon', $html);
        $this->assertStringContainsString('At 11:00 – 14:00 WIB', $html);
    }

    public function test_cards_keep_their_look_until_an_icon_is_chosen(): void
    {
        foreach (['classic', 'mocha-glass'] as $design) {
            $this->assertStringNotContainsString('fa-solid fa-ring', $this->page($design, 'cards', self::EVENTS), "[{$design}]");
            $this->assertStringContainsString('fa-solid fa-mosque', $this->page($design, 'cards', [[...self::EVENTS[0], 'icon' => 'mosque']]), "[{$design}]");
        }

        // Botanical Cream always showed an icon picked from the title.
        $this->assertStringContainsString('fa-solid fa-ring bc-event__icon', $this->page('botanical-cream', 'list', self::EVENTS));
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function render(string $design, string $variant, array $events): array
    {
        return app(InvitationRenderer::class)->forTemplatePreview($this->template($design, $variant, $events))->sections[0]['config'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function page(string $design, string $variant, array $events): string
    {
        return $this->get(route('templates.preview', $this->template($design, $variant, $events)))->assertOk()->getContent();
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function template(string $design, string $variant, array $events): InvitationTemplate
    {
        return InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => [['type' => 'event', 'variant' => $variant]]],
            'sample_content' => ['event' => ['heading' => 'Waktu & Tempat', 'events' => $events]],
        ]);
    }
}
