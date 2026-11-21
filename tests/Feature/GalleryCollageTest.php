<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gallery layout "collage": photos as overlapping prints, two side by side and one across, in every design.
 */
class GalleryCollageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_design_lays_the_photos_out_in_sets_of_three(): void
    {
        foreach (['classic', 'mocha-glass', 'botanical-cream'] as $design) {
            $html = $this->gallery($design, 4);

            $this->assertStringContainsString('class="inv-collage"', $html, "[{$design}]");
            $this->assertSeeInOrderIn($html, ['inv-collage__item--0', 'inv-collage__item--1', 'inv-collage__item--2', 'inv-collage__item--0 inv-collage__item--alone'], $design);
        }
    }

    public function test_classic_prints_are_pictures_and_the_design_packs_open_the_lightbox(): void
    {
        $this->assertStringContainsString('<figure class="inv-collage__item inv-collage__item--0"', $this->gallery('classic', 2));
        $this->assertStringNotContainsString('inv-collage__item--alone', $this->gallery('classic', 2));

        foreach (['mocha-glass', 'botanical-cream'] as $design) {
            $html = $this->gallery($design, 2);

            $this->assertMatchesRegularExpression('/<button type="button" class="inv-collage__item inv-collage__item--1"[^>]*x-on:click="index = 1; zoom = true" aria-label="'.preg_quote(__('Enlarge photo'), '/').'"/', $html, "[{$design}]");
        }
    }

    public function test_botanical_cream_shows_six_prints_until_see_all(): void
    {
        $html = $this->gallery('botanical-cream', 8);

        $this->assertSame(2, substr_count($html, 'x-show="all" x-cloak'));
        $this->assertStringContainsString(__('See all photos (:count)', ['count' => 8]), $html);
    }

    private function assertSeeInOrderIn(string $html, array $needles, string $design): void
    {
        $offset = 0;

        foreach ($needles as $needle) {
            $found = strpos($html, $needle, $offset);
            $this->assertNotFalse($found, "[{$design}] misses {$needle} in order.");
            $offset = $found + strlen($needle);
        }
    }

    private function gallery(string $design, int $photos): string
    {
        $images = array_map(fn (int $i): string => "/images/sample-{$i}.jpg", range(1, $photos));
        $template = InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => ['cover', ['type' => 'gallery', 'variant' => 'collage']]],
            'sample_content' => ['gallery' => ['heading' => 'Galeri', 'images' => $images]],
        ]);

        return $this->get(route('templates.preview', $template))->assertOk()->getContent();
    }
}
