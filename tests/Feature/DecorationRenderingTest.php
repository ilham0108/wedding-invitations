<?php

namespace Tests\Feature;

use App\Enums\IllustrationCategory;
use App\Livewire\TemplateBuilder;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Support\CssUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DecorationRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_carries_the_divider_and_texture(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover', 'quote', 'closing']],
            'decoration' => ['divider' => 'flourish', 'texture' => 'linen'],
        ]);

        $this->get(route('templates.preview', $template))
            ->assertOk()
            ->assertSee('data-inv-divider="flourish" data-inv-texture="linen"', false)
            ->assertSee('data-section="closing"', false);
    }

    public function test_unknown_values_fall_back_to_the_defaults(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover']],
            'decoration' => ['divider' => 'zigzag', 'texture' => 'url(evil)'],
        ]);

        $this->get(route('templates.preview', $template))
            ->assertSee('data-inv-divider="line" data-inv-texture="none"', false);
    }

    public function test_admins_choose_the_divider_and_texture_in_the_builder(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover']]]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template])
            ->set('panel', 'theme')
            ->assertSee('Divider')
            ->assertSee('Texture')
            ->call('setDecoration', 'divider', 'dots')
            ->call('setDecoration', 'texture', 'grain')
            ->call('setDecoration', 'texture', 'marble')
            ->assertSet('decoration.texture', 'grain')
            ->call('save');

        $decoration = $template->fresh()->decoration;
        $this->assertSame('dots', $decoration['divider']);
        $this->assertSame('grain', $decoration['texture']);
    }

    public function test_a_built_in_heading_ornament_is_drawn_in_every_design(): void
    {
        foreach (['classic', 'mocha-glass', 'botanical-cream'] as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => ['cover', 'countdown']],
                'decoration' => ['heading_ornament' => 'heart', 'heading_ornament_size' => 'large'],
            ]);

            $this->get(route('templates.preview', $template))
                ->assertOk()
                ->assertSee('data-inv-heading-ornament="mask"', false)
                ->assertSee('data-inv-heading-ornament-size="large"', false)
                ->assertSee('--inv-heading-ornament: '.CssUrl::wrap('/images/heading-ornaments/heart.svg'));
        }

        $this->assertFileExists(public_path('images/heading-ornaments/heart.svg'));
    }

    public function test_the_default_keeps_each_design_own_mark_and_none_hides_it(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'countdown']]]);
        $this->get(route('templates.preview', $template))->assertSee('data-inv-heading-ornament="design"', false)->assertDontSee('--inv-heading-ornament:', false);

        $template->update(['decoration' => ['heading_ornament' => 'none']]);
        $this->get(route('templates.preview', $template))->assertSee('data-inv-heading-ornament="none"', false);
    }

    public function test_admins_pick_a_library_ornament_under_headings_by_reference_only(): void
    {
        $ornament = Illustration::factory()->create(['category' => IllustrationCategory::Ornament]);
        $cover = Illustration::factory()->create(['category' => IllustrationCategory::Cover]);
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'countdown']]]);

        $builder = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template])
            ->set('panel', 'theme')
            ->assertSee(__('Ornament under headings'))
            // The image cannot be set directly, only picked from the library's ornaments.
            ->call('setDecoration', 'heading_ornament', 'image')
            ->call('setDecoration', 'heading_ornament_image', $ornament->ulid)
            ->assertSet('decoration.heading_ornament', 'design')
            ->set('headingOrnamentPicker', true)
            ->assertSee($ornament->name);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template])
            ->call('chooseHeadingOrnamentImage', $cover->ulid)
            ->assertNotFound();

        $builder->call('chooseHeadingOrnamentImage', $ornament->ulid)
            ->assertSet('decoration.heading_ornament', 'image')
            ->assertSet('headingOrnamentPicker', false)
            ->call('setDecoration', 'heading_ornament_size', 'small')
            ->call('save');

        $this->assertEquals(['heading_ornament' => 'image', 'heading_ornament_image' => $ornament->ulid, 'heading_ornament_size' => 'small'], array_intersect_key($template->fresh()->decoration, array_flip(['heading_ornament', 'heading_ornament_image', 'heading_ornament_size'])));

        $this->get(route('templates.preview', $template))
            ->assertSee('data-inv-heading-ornament="image"', false)
            ->assertSee('--inv-heading-ornament: '.CssUrl::wrap($ornament->url()));

        // A hidden image falls back to the design's own mark instead of a broken picture.
        $ornament->update(['is_active' => false]);
        $this->get(route('templates.preview', $template))->assertSee('data-inv-heading-ornament="design"', false);
    }
}
