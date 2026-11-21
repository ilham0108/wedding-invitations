<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use App\Support\CoverTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cover field "title_layout": the names on one line, or on three lines (stacked or staggered), in every design.
 */
class CoverTitleLayoutTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'mocha-glass', 'botanical-cream'];

    public function test_three_line_layouts_split_the_names_at_the_ampersand_in_every_design(): void
    {
        foreach (self::DESIGNS as $design) {
            foreach (['stacked', 'cascade'] as $layout) {
                $html = $this->cover($design, ['title' => 'Raka & Sinta', 'title_layout' => $layout]);

                $this->assertMatchesRegularExpression(
                    '/<span class="inv-title-lines inv-title-lines--'.$layout.'">\s*<span class="inv-title-line">Raka<\/span>\s*<span class="inv-title-line inv-title-amp[^"]*">&amp;<\/span>\s*<span class="inv-title-line">Sinta<\/span>/',
                    $html,
                    "[{$design}, {$layout}]",
                );
            }
        }
    }

    public function test_the_design_default_keeps_each_designs_own_lines(): void
    {
        $this->assertStringNotContainsString('inv-title-lines', $this->cover('classic', ['title' => 'Raka & Sinta']));
        $this->assertStringContainsString('<span>Raka</span><br>&amp;<br><span>Sinta</span>', $this->cover('mocha-glass', ['title' => 'Raka & Sinta']));
        $this->assertStringContainsString('<span class="bc-names__amp">&amp;</span>Sinta', $this->cover('botanical-cream', ['title' => 'Raka & Sinta']));
    }

    public function test_one_line_and_titles_without_an_ampersand_stay_as_typed(): void
    {
        $html = $this->cover('mocha-glass', ['title' => 'Raka & Sinta', 'title_layout' => 'line']);
        $this->assertStringContainsString('Raka &amp; Sinta', $html);
        $this->assertStringNotContainsString('<span>Raka</span><br>', $html);

        $this->assertStringNotContainsString('inv-title-lines', $this->cover('classic', ['title' => 'Pernikahan Raka', 'title_layout' => 'cascade']));
        $this->assertNull(CoverTitle::parts(' & Sinta'));
        $this->assertSame(['Raka', 'Sinta & Co'], CoverTitle::parts('Raka  &  Sinta & Co'));
        $this->assertNull(CoverTitle::layout('diagonal'));
    }

    /**
     * @param  array<string, string>  $cover
     */
    private function cover(string $design, array $cover): string
    {
        $template = InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => ['cover', 'event']],
            'default_configuration' => ['cover' => $cover],
            'sample_content' => ['cover' => ['title' => $cover['title']]],
        ]);

        return $this->get(route('templates.preview', $template))->assertOk()->getContent();
    }
}
