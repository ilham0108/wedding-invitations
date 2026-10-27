<?php

namespace Tests\Feature;

use App\Enums\IllustrationCategory;
use App\Livewire\IllustrationManager;
use App\Livewire\TemplateBuilder;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\IllustrationService;
use App\Services\Invitation\InvitationService;
use App\Services\Invitation\Ornaments;
use App\Services\Template\TemplateSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OrnamentTest extends TestCase
{
    use RefreshDatabase;

    private function ornament(string $file = 'ornament-flower-corner.svg'): Illustration
    {
        return Illustration::query()->create([
            'category' => IllustrationCategory::Ornament, 'name' => 'Bunga', 'disk' => null, 'path' => 'illustrations/'.$file,
            'width' => 400, 'height' => 400, 'is_premium' => false, 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(array $overrides = []): array
    {
        return [
            'image' => '/illustrations/ornament-flower-corner.svg', 'x' => 0, 'y' => 100, 'width' => 35, 'anchor' => 'bottom-left',
            'flip' => false, 'layer' => 'front', 'enter' => 'fade-up', 'duration' => 1.6, 'delay' => 0, 'motion' => 'sway', ...$overrides,
        ];
    }

    public function test_ornaments_are_cleaned_and_clamped(): void
    {
        $clean = app(Ornaments::class)->clean([
            ['image' => '/illustrations/a.svg', 'x' => '120.456', 'y' => -400, 'width' => 999, 'anchor' => 'center', 'flip' => 1, 'layer' => 'back',
                'enter' => 'zoom-in-up', 'duration' => 30, 'delay' => -2, 'motion' => 'float'],
            ['image' => 'javascript:alert(1)'],
            ['image' => '/illustrations/b.svg', 'anchor' => 'nowhere', 'enter' => 'explode', 'motion' => 'spin', 'layer' => 'middle', 'x' => 'abc'],
        ]);

        $this->assertSame([
            ['image' => '/illustrations/a.svg', 'x' => 120.46, 'y' => -50.0, 'width' => 150.0, 'anchor' => 'center', 'flip' => true, 'layer' => 'back',
                'enter' => 'zoom-in-up', 'duration' => 8.0, 'delay' => 0.0, 'motion' => 'float'],
            ['image' => '/illustrations/b.svg', 'x' => 0.0, 'y' => 100.0, 'width' => 35.0, 'anchor' => 'bottom-left', 'flip' => false, 'layer' => 'front',
                'enter' => 'fade-up', 'duration' => 1.6, 'delay' => 0.0, 'motion' => 'sway'],
        ], $clean);

        $this->assertCount(60, app(Ornaments::class)->clean(array_fill(0, 80, ['image' => '/x.svg'])));
    }

    public function test_the_template_schema_keeps_ornaments_per_section(): void
    {
        $definition = app(TemplateSchema::class)->validate(['schema' => ['sections' => [
            ['type' => 'cover', 'ornaments' => [$this->item(['extra' => 'dropped'])]],
            'closing',
        ]]]);

        $this->assertSame([$this->item(['x' => 0.0, 'y' => 100.0, 'width' => 35.0, 'delay' => 0.0])], $definition['schema']['sections'][0]['ornaments']);
        $this->assertSame(['type' => 'closing', 'variant' => 'default', 'background' => 'default'], $definition['schema']['sections'][1]);

        $this->expectException(ValidationException::class);
        app(TemplateSchema::class)->validate(['schema' => ['sections' => [['type' => 'cover', 'ornaments' => [$this->item(['anchor' => 'nowhere'])]]]]]);
    }

    public function test_the_first_template_wide_ornament_format_is_upgraded_to_every_section(): void
    {
        $definition = app(TemplateSchema::class)->validate([
            'schema' => ['sections' => ['cover', 'closing']],
            'decoration' => ['ornaments' => [['image' => '/illustrations/a.svg', 'position' => 'top-right', 'size' => 'large', 'motion' => 'rise', 'flip' => true]]],
        ]);

        foreach ($definition['schema']['sections'] as $section) {
            $this->assertSame([[
                'image' => '/illustrations/a.svg', 'x' => 100.0, 'y' => 0.0, 'width' => 52.0, 'anchor' => 'top-right', 'flip' => true,
                'layer' => 'front', 'enter' => 'fade-down', 'duration' => 1.3, 'delay' => 0.0, 'motion' => 'sway',
            ]], $section['ornaments']);
        }

        $this->assertArrayNotHasKey('ornaments', $definition['decoration']);
    }

    public function test_admins_arrange_the_ornaments_of_each_section_in_the_builder(): void
    {
        $ornament = $this->ornament();
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'event', 'closing']]]);

        $builder = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template]);
        [$cover, $event, $closing] = array_column($builder->get('sections'), 'key');

        $builder->set('ornamentPicker', true)
            ->assertSee($ornament->url(), false)
            ->call('addOrnament', $ornament->ulid)
            ->call('addOrnament', $ornament->ulid)
            ->call('updateOrnament', 0, 'width', '48')
            ->call('updateOrnament', 0, 'enter', 'zoom-in-right')
            ->call('updateOrnament', 0, 'delay', '1.5')
            ->call('updateOrnament', 0, 'layer', 'back')
            ->call('updateOrnament', 0, 'image', '/evil.svg')
            ->call('duplicateOrnament', 1)
            ->call('moveOrnament', 2, -1)
            ->call('removeOrnament', 1)
            ->call('select', $closing)
            ->call('copyOrnamentsFrom', $cover)
            ->call('save');

        $sections = $template->fresh()->schema['sections'];
        $first = $sections[0]['ornaments'];

        $this->assertCount(2, $first);
        // assertEquals: JSON storage turns 0.0 into 0 and keeps its own key order.
        $this->assertEquals(['x' => 0, 'width' => 48, 'anchor' => 'bottom-left', 'flip' => false, 'layer' => 'back', 'enter' => 'zoom-in-right', 'delay' => 1.5],
            array_intersect_key($first[0], array_flip(['x', 'width', 'anchor', 'flip', 'layer', 'enter', 'delay'])));
        $this->assertSame('/illustrations/ornament-flower-corner.svg', $first[0]['image']);
        $this->assertEquals(['x' => 100, 'anchor' => 'bottom-right', 'flip' => true], array_intersect_key($first[1], array_flip(['x', 'anchor', 'flip'])));
        $this->assertArrayNotHasKey('ornaments', $sections[1]);
        $this->assertSame($first, $sections[2]['ornaments']);
    }

    public function test_only_library_ornaments_can_be_added(): void
    {
        $person = Illustration::query()->create([
            'category' => IllustrationCategory::Person, 'name' => 'Orang', 'disk' => null, 'path' => 'illustrations/person-bride.svg',
            'width' => 600, 'height' => 600, 'is_premium' => false, 'is_active' => true, 'sort_order' => 1,
        ]);
        $template = InvitationTemplate::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template])
            ->call('addOrnament', $person->ulid)
            ->assertNotFound();

        $this->assertSame($template->schema, $template->fresh()->schema);
    }

    public function test_sections_render_their_own_ornaments_in_every_design(): void
    {
        foreach (['classic', 'mocha-glass'] as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => [
                    ['type' => 'cover', 'ornaments' => [$this->item(['image' => '/illustrations/ornament-hanging-branch.svg', 'x' => 100, 'y' => 0, 'anchor' => 'top-right', 'flip' => true, 'enter' => 'fade-right', 'delay' => 1.2])]],
                    ['type' => 'event', 'ornaments' => [$this->item(['layer' => 'back', 'anchor' => 'center', 'x' => 50, 'y' => 50, 'width' => 80, 'enter' => 'zoom-out'])]],
                    'closing',
                ]],
            ]);

            $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

            // Mocha Glass shows the cover's ornaments on its sliding cover layer as well.
            $this->assertSame($design === 'classic' ? 1 : 2, substr_count($html, 'ornament-hanging-branch.svg'), "[{$design}]");
            $this->assertSame(1, substr_count($html, 'inv-ornaments--back'), "[{$design}]");
            // Placement and timing, with the mirrored entrance turned back the right way.
            $this->assertStringContainsString('left: 100%; top: 0%; width: 35%; translate: -100% -0%; --inv-ornament-origin: 0% 0%; --inv-ornament-from: translateX(40px); --inv-ornament-duration: 1.6s; --inv-ornament-delay: 1.2s;', $html);
            $this->assertStringContainsString('left: 50%; top: 50%; width: 80%; translate: -50% -50%;', $html);
            $this->assertStringContainsString('--inv-ornament-from: scale(1.3);', $html);
            $this->assertDoesNotMatchRegularExpression('/data-section="closing"[^>]*inv-has-ornaments/', $html);
        }
    }

    public function test_invitations_keep_the_ornaments_they_were_created_with(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => [['type' => 'cover', 'ornaments' => [$this->item()]], 'closing']]]);
        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'John & Jane', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $template->update(['schema' => ['sections' => ['cover', 'closing']]]);

        $this->assertCount(1, $invitation->sections()->first()->theme['ornaments']);
        $this->assertNull($invitation->sections()->skip(1)->first()->theme);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertSee('ornament-flower-corner.svg', false);
    }

    public function test_ornaments_in_use_are_hidden_instead_of_deleted(): void
    {
        $ornament = $this->ornament('ornament-side-vine.svg');
        InvitationTemplate::factory()->create(['schema' => ['sections' => [['type' => 'cover', 'ornaments' => [$this->item(['image' => $ornament->url()])]]]]]);

        $this->assertTrue(app(IllustrationService::class)->isInUse($ornament));
        $this->assertFalse(app(IllustrationService::class)->remove($ornament));
        $this->assertFalse($ornament->fresh()->is_active);
    }

    public function test_the_cover_background_can_zoom_out_or_drift(): void
    {
        foreach (['classic', 'mocha-glass'] as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => ['cover']],
                'sample_content' => ['cover' => ['background_image' => '/images/demo/cover.svg', 'background_motion' => 'zoom-out']],
            ]);

            $this->get(route('templates.preview', $template))->assertOk()->assertSee('inv-bg-motion--zoom-out', false);
        }
    }

    public function test_admins_upload_their_own_transparent_ornaments(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();

        // A transparent PNG, as exported from a design tool.
        $canvas = imagecreatetruecolor(400, 600);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagefilledellipse($canvas, 200, 400, 300, 300, imagecolorallocate($canvas, 120, 160, 110));
        $path = tempnam(sys_get_temp_dir(), 'orn').'.png';
        imagepng($canvas, $path);

        Livewire::actingAs($admin)
            ->test(IllustrationManager::class)
            ->set('uploadCategory', 'ornament')
            ->set('files', [UploadedFile::fake()->createWithContent('bunga-lili.png', file_get_contents($path))])
            ->call('saveUploads')
            ->assertHasNoErrors();

        $ornament = Illustration::query()->where('category', 'ornament')->sole();
        $this->assertSame('bunga-lili', $ornament->name);
        $this->assertStringEndsWith('.webp', $ornament->path);

        // The stored WebP keeps transparency: a corner pixel stays fully transparent.
        $webp = imagecreatefromstring(Storage::disk('public')->get($ornament->path));
        $this->assertSame(127, (imagecolorat($webp, 0, 0) >> 24) & 0x7F);

        Livewire::actingAs($admin)
            ->test(TemplateBuilder::class, ['template' => InvitationTemplate::factory()->create()])
            ->set('ornamentPicker', true)
            ->assertSee($ornament->url(), false);
    }
}
