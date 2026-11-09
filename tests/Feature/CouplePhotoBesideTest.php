<?php

namespace Tests\Feature;

use App\Livewire\TemplateSectionContent;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\SectionRegistry;
use App\Support\PhotoSide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Couple layout "photo-beside": each portrait beside the person's name, on the side set in the person's photo_side.
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

    public function test_each_person_chooses_the_side_of_their_photo(): void
    {
        foreach (self::DESIGNS as $design) {
            $html = $this->render($design, null, [[...self::BRIDE, 'photo_side' => 'right'], [...self::GROOM, 'photo_side' => 'left']]);

            $this->assertSame(1, substr_count($html, 'inv-person-beside--right'), "[{$design}]");
            $this->assertLessThan(strpos($html, 'Alya Salsabila'), strpos($html, 'inv-person-beside--right'), "[{$design}]");
        }
    }

    public function test_a_persons_side_wins_over_the_old_section_setting(): void
    {
        $html = $this->render('classic', 'right', [[...self::BRIDE, 'photo_side' => 'left'], self::GROOM]);

        $this->assertSame(1, substr_count($html, 'inv-person-beside--right'));
    }

    public function test_the_editor_shows_old_section_settings_per_person_and_new_people_start_left(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => [['type' => 'couple', 'variant' => 'photo-beside']]],
            'default_configuration' => ['couple' => ['photo_side' => 'alternate', 'people' => [self::BRIDE, self::GROOM]]],
        ]);
        $mount = fn (string $variant) => Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateSectionContent::class, [
            'templateUlid' => $template->ulid, 'type' => 'couple', 'group' => 'default_configuration', 'variant' => $variant,
        ]);

        $mount('photo-beside')
            ->assertSet('config.people.0.photo_side', 'left')
            ->assertSet('config.people.1.photo_side', 'right')
            ->assertSee('Right of the name')
            ->call('addItem', 'people')
            ->assertSet('config.people.2.photo_side', 'left')
            ->set('config.people.0.photo_side', 'right')
            ->assertHasNoErrors()
            ->set('config.people.0.photo_side', 'top')
            ->assertHasErrors('config.people.0.photo_side');

        $mount('stacked')->assertDontSee('Right of the name');
    }

    public function test_the_old_section_setting_can_still_be_right_or_alternate(): void
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
        $this->assertArrayNotHasKey('photo_side', app(SectionRegistry::class)->defaults('couple'));

        $rules = app(SectionRegistry::class)->rules('couple', 'config');
        $person = fn (string $side): array => ['config' => ['people' => [['photo_side' => $side]]]];
        $this->assertTrue(Validator::make($person('right'), $rules)->passes());
        $this->assertFalse(Validator::make($person('alternate'), $rules)->passes());

        $this->assertFalse(PhotoSide::isRight('left'));
        $this->assertTrue(PhotoSide::isRight('right'));
        $this->assertFalse(PhotoSide::isRight('"><script>'));
        $this->assertSame('', PhotoSide::classes('right', false));

        $people = PhotoSide::perPerson(['photo_side' => 'alternate', 'people' => [[], [], [], ['photo_side' => '"><script>']]]);
        $this->assertSame(['left', 'right', 'left', 'right'], array_column($people['people'], 'photo_side'));
        $this->assertArrayNotHasKey('photo_side', $people);
    }

    /**
     * @param  array<int, array<string, mixed>>  $people
     */
    private function render(string $design, ?string $sectionSide, array $people = [self::BRIDE, self::GROOM]): string
    {
        $template = InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => [['type' => 'couple', 'variant' => 'photo-beside']]],
            'sample_content' => ['couple' => [...array_filter(['photo_side' => $sectionSide]), 'people' => $people]],
        ]);

        return $this->get(route('templates.preview', $template))->assertOk()->getContent();
    }
}
