<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use App\Services\Invitation\SectionRegistry;
use App\Support\PhotoSide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Couple layout "photo-beside": each portrait beside the person's name, on the side set in photo_side.
 */
class CouplePhotoBesideTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'botanical-cream', 'mocha-glass'];

    private const BRIDE = ['name' => 'Alya Salsabila', 'parents' => 'Putri dari Bapak Rudi', 'photo' => '/illustrations/person-bride.svg'];

    private const GROOM = ['name' => 'Pradana Wicaksana', 'parents' => 'Putra dari Bapak Arif', 'photo' => '/illustrations/person-groom-suit.svg'];

    public function test_every_design_puts_the_portraits_beside_the_names(): void
    {
        foreach (self::DESIGNS as $design) {
            $html = $this->render($design, 'left');

            $this->assertSame(2, substr_count($html, 'inv-person-beside'), "[{$design}]");
            $this->assertStringNotContainsString('inv-person-beside--right', $html, "[{$design}]");
            $this->assertStringContainsString('Pradana Wicaksana', $html, "[{$design}]");
        }
    }

    public function test_the_photo_side_can_be_right_or_alternate(): void
    {
        foreach (self::DESIGNS as $design) {
            $this->assertSame(2, substr_count($this->render($design, 'right'), 'inv-person-beside--right'), "[{$design}]");

            // Alternate: the bride's photo on the left, the groom's on the right.
            $html = $this->render($design, 'alternate');
            $this->assertSame(1, substr_count($html, 'inv-person-beside--right'), "[{$design}]");
            $this->assertLessThan(strpos($html, 'inv-person-beside--right'), strpos($html, 'Alya Salsabila'), "[{$design}]");
        }
    }

    public function test_a_person_without_a_photo_keeps_the_designs_own_layout(): void
    {
        foreach (self::DESIGNS as $design) {
            $html = $this->render($design, 'left', [self::BRIDE, [...self::GROOM, 'photo' => null]]);

            $this->assertSame(1, substr_count($html, 'class="inv-person-beside') + substr_count($html, ' inv-person-beside"'), "[{$design}]");
        }
    }

    public function test_photo_side_is_limited_to_the_known_sides(): void
    {
        $this->assertSame(['side-by-side', 'stacked', 'photo-beside'], app(SectionRegistry::class)->variants('couple'));
        $this->assertSame('left', app(SectionRegistry::class)->defaults('couple')['photo_side']);

        $rules = app(SectionRegistry::class)->rules('couple', 'config');
        $this->assertTrue(Validator::make(['config' => ['photo_side' => 'alternate']], $rules)->passes());
        $this->assertFalse(Validator::make(['config' => ['photo_side' => 'top']], $rules)->passes());

        $this->assertFalse(PhotoSide::isRight('left', 1));
        $this->assertTrue(PhotoSide::isRight('right', 0));
        $this->assertSame([false, true, false], [PhotoSide::isRight('alternate', 0), PhotoSide::isRight('alternate', 1), PhotoSide::isRight('alternate', 2)]);
        $this->assertFalse(PhotoSide::isRight('"><script>', 1));
        $this->assertSame('', PhotoSide::classes('right', 0, false));
    }

    /**
     * @param  array<int, array<string, mixed>>  $people
     */
    private function render(string $design, string $side, array $people = [self::BRIDE, self::GROOM]): string
    {
        $template = InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => [['type' => 'couple', 'variant' => 'photo-beside']]],
            'sample_content' => ['couple' => ['photo_side' => $side, 'people' => $people]],
        ]);

        return $this->get(route('templates.preview', $template))->assertOk()->getContent();
    }
}
