<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cover field "arabic_text": a line in Arabic letters above the title; the cover's texts also read in either direction.
 */
class CoverArabicTextTest extends TestCase
{
    use RefreshDatabase;

    private const BASMALA = 'بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيمِ';

    public function test_every_design_shows_the_arabic_line_above_the_title(): void
    {
        foreach (['classic', 'mocha-glass', 'botanical-cream'] as $design) {
            foreach (['centered', 'framed'] as $variant) {
                $html = $this->cover($design, $variant, ['arabic_text' => self::BASMALA, 'title' => 'Ahmad & Siti']);

                $this->assertMatchesRegularExpression('#<p class="inv-cover-arabic" lang="ar" dir="rtl"\s*>'.preg_quote(self::BASMALA, '#').'</p>#u', $html, "[{$design} {$variant}]");
                // Right above the title block (Mocha Glass also shows the names earlier, on its desktop side panel).
                $arabic = strpos($html, self::BASMALA);
                $this->assertLessThan(800, strpos($html, 'Ahmad', $arabic) - $arabic, "[{$design} {$variant}] the title follows the Arabic line");
                $this->assertMatchesRegularExpression('/<h[12] (class="[^"]+" )?dir="auto"/', $html, "[{$design} {$variant}]");
            }
        }
    }

    public function test_an_empty_field_adds_nothing(): void
    {
        $this->assertStringNotContainsString('inv-cover-arabic"', $this->cover('classic', 'centered', ['title' => 'Ahmad & Siti']));
    }

    public function test_the_text_is_escaped(): void
    {
        $this->assertStringContainsString('&lt;script&gt;', $this->cover('classic', 'centered', ['arabic_text' => '<script>alert(1)</script>']));
    }

    /**
     * @param  array<string, mixed>  $cover
     */
    private function cover(string $design, string $variant, array $cover): string
    {
        $template = InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => [['type' => 'cover', 'variant' => $variant], 'closing']],
            'sample_content' => ['cover' => $cover],
        ]);

        return $this->get(route('templates.preview', $template))->assertOk()->getContent();
    }
}
