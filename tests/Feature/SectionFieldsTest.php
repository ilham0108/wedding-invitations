<?php

namespace Tests\Feature;

use App\Livewire\TemplateSectionContent;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationRenderer;
use App\Services\Invitation\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fields added for design packs: social links, event notices and calendar links,
 * gift registry, structured dress code, inviters, and the health protocol section.
 */
class SectionFieldsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, array<string, mixed>>  $samples
     * @return array<string, array<string, mixed>> section config by type
     */
    private function render(array $samples): array
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => array_keys($samples)],
            'sample_content' => $samples,
        ]);

        return collect(app(InvitationRenderer::class)->forTemplatePreview($template)->sections)
            ->mapWithKeys(fn (array $section): array => [$section['type'] => $section['config']])
            ->all();
    }

    public function test_social_links_and_registry_links_only_keep_safe_urls(): void
    {
        $config = $this->render([
            'couple' => ['people' => [['name' => 'Arina', 'instagram' => 'https://instagram.com/arina', 'facebook' => 'javascript:alert(1)', 'tiktok' => '']]],
            'gift' => ['wishlist' => [['item' => 'Rice cooker', 'url' => 'javascript:alert(1)'], ['item' => 'Sprei', 'url' => 'https://shop.test/sprei']]],
        ]);

        $this->assertSame(['instagram' => 'https://instagram.com/arina'], $config['couple']['people'][0]['socials']);
        $this->assertNull($config['gift']['wishlist'][0]['url']);
        $this->assertSame('https://shop.test/sprei', $config['gift']['wishlist'][1]['url']);
    }

    public function test_calendar_links_use_the_invitation_timezone(): void
    {
        $events = $this->render(['event' => ['events' => [
            ['title' => 'Resepsi', 'date' => '2026-12-31', 'start_time' => '10:00', 'end_time' => '14:00', 'venue' => 'Grand Ballroom', 'add_to_calendar' => true],
            ['title' => 'Akad', 'date' => '2026-12-31', 'add_to_calendar' => true],
            ['title' => 'Private', 'date' => '2026-12-31', 'start_time' => '08:00', 'add_to_calendar' => false],
        ]]])['event']['events'];

        // Template previews use Asia/Jakarta (UTC+7).
        $this->assertStringContainsString('dates=20261231T030000Z%2F20261231T070000Z', $events[0]['calendar_url']);
        $this->assertStringContainsString('text=Resepsi', $events[0]['calendar_url']);
        $this->assertStringContainsString('dates=20261231%2F20270101', $events[1]['calendar_url']);
        $this->assertNull($events[2]['calendar_url']);
    }

    public function test_line_based_fields_become_lists(): void
    {
        $config = $this->render([
            'dress_code' => ['men' => 'Batik', 'notes' => "Parkir luas\n\n  Petugas parkir  \r\n"],
            'closing' => ['inviters' => "Kel. Bpk Sumarni\nKel. Bpk Samuel"],
        ]);

        $this->assertSame(['Parkir luas', 'Petugas parkir'], $config['dress_code']['notes']);
        $this->assertSame(['Kel. Bpk Sumarni', 'Kel. Bpk Samuel'], $config['closing']['inviters']);
    }

    public function test_health_protocol_has_translated_defaults_and_whitelisted_icons(): void
    {
        app()->setLocale('id');
        $defaults = app(SectionRegistry::class)->defaults('health_protocol');
        app()->setLocale('en');

        $this->assertSame('Protokol Kesehatan', $defaults['heading']);
        $this->assertSame('Datang dalam keadaan sehat', $defaults['items'][0]['text']);
        $this->assertSame('healthy', $defaults['items'][0]['icon']);

        $items = $this->render(['health_protocol' => ['items' => [['icon' => 'mask', 'text' => 'Masker'], ['icon' => '" onclick="x', 'text' => 'Evil']]]])['health_protocol']['items'];

        $this->assertSame('fa-solid fa-head-side-mask', $items[0]['icon_class']);
        $this->assertSame('fa-solid fa-circle-check', $items[1]['icon_class']);
    }

    public function test_classic_views_show_the_new_fields(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['quote', 'gift', 'closing', 'health_protocol']],
            'sample_content' => [
                'quote' => ['arabic_text' => 'وَمِنْ اٰيٰتِهٖٓ', 'text' => 'Ar-Rum 21'],
                'gift' => ['wishlist_heading' => 'Daftar Kado', 'wishlist' => [['item' => 'Rice Cooker', 'url' => 'https://shop.test']]],
                'closing' => ['inviters' => 'Kel. Bpk Sumarni'],
            ],
        ]);

        $this->get(route('templates.preview', $template))
            ->assertOk()
            ->assertSee('وَمِنْ اٰيٰتِهٖٓ')
            ->assertSee('Rice Cooker')
            ->assertSee('Kel. Bpk Sumarni')
            ->assertSee('fa-head-side-cough', false);
    }

    public function test_frame_style_is_whitelisted(): void
    {
        $this->assertSame('solid', $this->render(['cover' => ['frame_style' => 'solid']])['cover']['frame_style']);
        $this->assertSame('blur', $this->render(['cover' => ['frame_style' => '" onload="x']])['cover']['frame_style']);
    }

    public function test_framed_covers_use_the_chosen_frame_style_in_every_design(): void
    {
        foreach (['classic' => 'inv-frame--transparent', 'mocha-glass' => 'mg-framebox--transparent'] as $design => $class) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => [['type' => 'cover', 'variant' => 'framed'], 'closing']],
                'sample_content' => ['cover' => ['title' => 'Arina & Bagas', 'frame_style' => 'transparent']],
            ]);

            $this->get(route('templates.preview', $template))->assertOk()->assertSee($class, false);
        }

        $centered = InvitationTemplate::factory()->create([
            'design' => 'mocha-glass',
            'schema' => ['sections' => [['type' => 'cover', 'variant' => 'centered']]],
        ]);

        $this->get(route('templates.preview', $centered))->assertOk()->assertDontSee('mg-framebox', false);
    }

    public function test_the_frame_style_field_only_shows_for_the_framed_layout(): void
    {
        $template = InvitationTemplate::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $mount = fn (string $variant) => Livewire::actingAs($admin)->test(TemplateSectionContent::class, [
            'templateUlid' => $template->ulid, 'type' => 'cover', 'group' => 'default_configuration', 'variant' => $variant,
        ]);

        $mount('framed')->assertSee('Frame style')->assertSee('Transparent (outline only)');
        $mount('centered')->assertDontSee('Frame style');
    }

    public function test_every_section_but_the_cover_has_a_background_photo_field(): void
    {
        $registry = app(SectionRegistry::class);

        foreach ($registry->types() as $type) {
            $keys = array_column($registry->fields($type), 'key');

            $type === 'cover'
                ? $this->assertNotContains('background_photo', $keys)
                : $this->assertContains('background_photo', $keys, "Section [{$type}] has no background photo field.");
        }

        $this->assertNull($registry->defaults('event')['background_photo']);
        $this->assertArrayNotHasKey('background_photo', $registry->defaults('cover'));
    }

    public function test_section_background_photos_change_the_page_backdrop_in_every_design(): void
    {
        foreach (['classic', 'mocha-glass'] as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => ['cover', 'couple', 'event', 'gallery', 'closing']],
                'sample_content' => [
                    'couple' => ['background_photo' => '/images/demo/gallery-1.svg'],
                    'gallery' => ['background_photo' => 'javascript:alert(1)'],
                    'closing' => ['background_photo' => '/images/demo/gallery-1.svg'],
                ],
            ]);

            $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

            $this->assertStringContainsString('data-inv-backdrop-root', $html);
            $this->assertSame(1, substr_count($html, 'class="inv-backdrop__layer"'), "[{$design}] should have one layer per distinct photo.");
            $this->assertMatchesRegularExpression('/data-section="couple"\s+data-inv-backdrop="\/images\/demo\/gallery-1.svg"/', $html);
            $this->assertStringNotContainsString('javascript:alert', $html);
        }

        $plain = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'event']]]);

        $this->get(route('templates.preview', $plain))->assertOk()->assertDontSee('data-inv-backdrop-root', false);
    }

    public function test_the_overlay_over_a_background_photo_is_chosen_per_section(): void
    {
        foreach (['classic', 'mocha-glass'] as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => ['cover', 'couple', ['type' => 'event', 'background' => 'muted'], 'quote', 'closing']],
                'sample_content' => [
                    'couple' => ['background_photo' => '/images/demo/gallery-1.svg', 'background_overlay' => 'light'],
                    'event' => ['background_photo' => '/images/demo/gallery-1.svg', 'background_overlay' => 'medium'],
                    'quote' => ['background_photo' => '/images/demo/gallery-1.svg', 'background_overlay' => 'bogus'],
                ],
            ]);

            $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

            // A plain section is covered by the page overlay it chose.
            $this->assertMatchesRegularExpression('/data-section="couple"[^>]*data-inv-backdrop-overlay="light"/', $html, "[{$design}]");
            $this->assertDoesNotMatchRegularExpression('/data-section="couple"[^>]*data-inv-tint/', $html, "[{$design}]");
            // A colored section tints the photo itself, so the page overlay is off for it.
            $this->assertMatchesRegularExpression('/data-section="event"[^>]*data-inv-backdrop-overlay="none"\s+data-inv-tint="medium"/', $html, "[{$design}]");
            // Unknown values fall back to the strong overlay; Mocha Glass always colors the quote section.
            $quote = $design === 'classic' ? 'data-inv-backdrop-overlay="strong"' : 'data-inv-backdrop-overlay="none"\s+data-inv-tint="strong"';
            $this->assertMatchesRegularExpression('/data-section="quote"[^>]*'.$quote.'/', $html, "[{$design}]");
            $this->assertDoesNotMatchRegularExpression('/data-section="closing"[^>]*data-inv-backdrop/', $html, "[{$design}]");
        }
    }

    public function test_sections_without_a_background_photo_have_no_backdrop(): void
    {
        $plain = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'event']]]);

        $this->get(route('templates.preview', $plain))->assertOk()->assertDontSee('data-inv-backdrop-root', false);
    }
}
