<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use App\Services\Invitation\SectionFonts;
use App\Services\Template\TemplateSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Section "fonts": a section's own heading/body font and weights (builder Section panel); empty follows the theme.
 */
class SectionFontTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'botanical-cream', 'mocha-glass'];

    private const FONTS = ['font_heading' => 'Marcellus', 'font_body' => 'Lora', 'body_weight' => '600'];

    public function test_sections_follow_the_theme_until_they_get_their_own_fonts(): void
    {
        $html = $this->get(route('templates.preview', InvitationTemplate::factory()->create()))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-inv-body-font', $html);
        $this->assertStringNotContainsString('marcellus', $html);
    }

    public function test_every_design_renders_a_sections_fonts_and_loads_them(): void
    {
        foreach (self::DESIGNS as $design) {
            $html = $this->get(route('templates.preview', $this->template($design)))->assertOk()->getContent();

            $this->assertStringContainsString('--inv-font-heading: &#039;Marcellus&#039;, &#039;Amiri&#039;, Georgia, serif; --inv-font-body: &#039;Lora&#039;, &#039;Amiri&#039;, Georgia, serif; --inv-body-weight: 600;', $html, "[{$design}]");
            $this->assertStringContainsString('data-inv-body-font', $html, "[{$design}]");
            $this->assertStringContainsString('data-inv-body-weight="600"', $html, "[{$design}]");
            // The page's font stylesheet includes the section's fonts.
            $this->assertMatchesRegularExpression('/fonts\.bunny\.net[^"]*marcellus:400/', $html, "[{$design}]");
        }
    }

    public function test_the_mocha_glass_cover_layer_uses_the_cover_fonts(): void
    {
        $template = InvitationTemplate::factory()->create([
            'design' => 'mocha-glass',
            'schema' => ['sections' => [['type' => 'cover', 'fonts' => ['font_heading' => 'Marcellus', 'heading_weight' => '400']], 'event']],
        ]);

        $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="mg-cover"[^>]*data-inv-heading-weight="400"[^>]*style="[^"]*--inv-font-heading: &#039;Marcellus&#039;/s', $html);
    }

    public function test_the_builder_sets_and_clears_section_fonts(): void
    {
        $template = InvitationTemplate::factory()->create();
        $builder = Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template]);
        $event = $builder->get('sections')[1]['key'];

        $builder->call('select', $event)
            ->call('setSectionFont', $event, 'font_heading', 'Cinzel Decorative')
            ->assertDispatched('template-preview-refresh')
            // Only the weights the section's font loads (Cinzel Decorative: 400, 700).
            ->assertViewHas('sectionFontWeights', fn (array $weights): bool => $weights === ['heading_weight' => ['400', '700'], 'body_weight' => ['300', '400', '500', '600', '700']])
            ->call('setSectionFont', $event, 'heading_weight', '700')
            ->call('setSectionFont', $event, 'font_body', 'Not A Font')
            ->call('setSectionFont', $event, 'body_weight', '650')
            ->call('setSectionFont', $event, 'color', 'red')
            ->assertSet('sections.1.fonts', ['font_heading' => 'Cinzel Decorative', 'heading_weight' => '700'])
            ->call('save');

        $this->assertSame(['font_heading' => 'Cinzel Decorative', 'heading_weight' => '700'], $template->fresh()->schema['sections'][1]['fonts']);
        $this->assertArrayNotHasKey('fonts', $template->fresh()->schema['sections'][0]);

        $builder->call('setSectionFont', $event, 'font_heading', 'default')
            ->call('setSectionFont', $event, 'heading_weight', 'default')
            ->assertSet('sections.1.fonts', [])
            ->call('save');

        $this->assertArrayNotHasKey('fonts', $template->fresh()->schema['sections'][1]);
    }

    public function test_the_schema_rejects_unknown_fonts_and_weights(): void
    {
        foreach ([['font_heading' => 'Comic Sans'], ['body_weight' => '650'], 'Lora'] as $fonts) {
            try {
                app(TemplateSchema::class)->validate(['schema' => ['sections' => [['type' => 'cover', 'fonts' => $fonts]]]]);
                $this->fail('Expected a validation error for '.json_encode($fonts));
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $canonical = app(TemplateSchema::class)->validate(['schema' => ['sections' => [['type' => 'cover', 'fonts' => ['font_heading' => 'Lora', 'body_weight' => 'default']]]]]);
        $this->assertSame(['font_heading' => 'Lora'], $canonical['schema']['sections'][0]['fonts']);
    }

    public function test_style_values_come_from_the_font_registry_only(): void
    {
        $fonts = app(SectionFonts::class);

        $this->assertSame([], $fonts->clean(['font_heading' => "Lora'; } body { color: red", 'heading_weight' => '700;']));
        $this->assertSame('', $fonts->style([]));
        $this->assertSame([], $fonts->clean('Lora'));
    }

    public function test_invitations_keep_the_template_section_fonts(): void
    {
        $template = $this->template('classic');

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Arina & Bagas', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->assertEquals(self::FONTS, $invitation->sections()->where('type', 'event')->first()->theme['fonts']);
        $this->assertArrayNotHasKey('fonts', $invitation->sections()->where('type', 'cover')->first()->theme ?? []);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertSee('data-inv-body-weight="600"', false)
            ->assertSee('marcellus:400', false);
    }

    private function template(string $design): InvitationTemplate
    {
        return InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => ['cover', ['type' => 'event', 'fonts' => self::FONTS], 'closing']],
        ]);
    }
}
