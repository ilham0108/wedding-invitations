<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use App\Services\Invitation\SectionColors;
use App\Services\Template\TemplateSchema;
use App\Support\Contrast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Section "colors": a section's own heading and text color (builder Section panel); empty follows the theme.
 */
class SectionColorTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'botanical-cream', 'mocha-glass'];

    private const COLORS = ['heading' => '#7a4b2a', 'text' => '#3d2b1f'];

    public function test_sections_follow_the_theme_until_they_get_their_own_colors(): void
    {
        $html = $this->get(route('templates.preview', InvitationTemplate::factory()->create()))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-inv-text-color', $html);
        $this->assertStringNotContainsString('--inv-heading-color', $html);
    }

    public function test_every_design_renders_a_sections_colors(): void
    {
        foreach (self::DESIGNS as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => ['cover', ['type' => 'event', 'colors' => self::COLORS, 'fonts' => ['font_heading' => 'Lora']], 'closing']],
            ]);

            $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

            // Fonts and colors share the one style attribute.
            $this->assertStringContainsString('--inv-font-heading: &#039;Lora&#039;, Georgia, serif; --inv-heading-color: #7a4b2a; --inv-section-text: #3d2b1f;', $html, "[{$design}]");
            $this->assertStringContainsString('data-inv-heading-color', $html, "[{$design}]");
            $this->assertStringContainsString('data-inv-text-color', $html, "[{$design}]");
            $this->assertSame(1, substr_count($html, 'data-inv-text-color'), "[{$design}]");
        }
    }

    public function test_the_builder_sets_and_clears_section_colors_and_reports_contrast(): void
    {
        $template = InvitationTemplate::factory()->create(['theme' => ['background_color' => '#ffffff']]);
        $builder = Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template]);
        $event = $builder->get('sections')[1]['key'];

        $builder->call('select', $event)
            ->call('setSectionColor', $event, 'heading', '#7A4B2A')
            ->assertDispatched('template-preview-refresh')
            ->call('setSectionColor', $event, 'text', '#eeeeee')
            ->call('setSectionColor', $event, 'text', 'red')
            ->call('setSectionColor', $event, 'accent', '#000000')
            ->assertSet('sections.1.colors', ['heading' => '#7a4b2a', 'text' => '#eeeeee'])
            // The hex field shows the chosen color.
            ->assertSeeHtml('value="#7a4b2a" placeholder=')
            // A light gray on white is flagged; the dark brown heading passes.
            ->assertViewHas('sectionColorContrast', fn (array $contrast): bool => $contrast['text']['ratio'] < Contrast::TEXT && $contrast['heading']['ratio'] >= Contrast::LARGE_TEXT)
            ->assertSee(__('Contrast :ratio:1, below :minimum:1: hard to read for some guests.', ['ratio' => '1.2', 'minimum' => '4.5']))
            ->call('save');

        $this->assertEquals(['heading' => '#7a4b2a', 'text' => '#eeeeee'], $template->fresh()->schema['sections'][1]['colors']);

        $builder->call('setSectionColor', $event, 'heading', 'default')
            ->call('setSectionColor', $event, 'text', 'default')
            ->assertSet('sections.1.colors', [])
            ->call('save');

        $this->assertArrayNotHasKey('colors', $template->fresh()->schema['sections'][1]);
    }

    public function test_the_schema_rejects_invalid_colors(): void
    {
        foreach ([['heading' => 'red'], ['text' => '#12345'], ['text' => '#123456;}']] as $colors) {
            try {
                app(TemplateSchema::class)->validate(['schema' => ['sections' => [['type' => 'cover', 'colors' => $colors]]]]);
                $this->fail('Expected a validation error for '.json_encode($colors));
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $canonical = app(TemplateSchema::class)->validate(['schema' => ['sections' => [['type' => 'cover', 'colors' => ['heading' => '#ABCDEF', 'text' => null]]]]]);
        $this->assertSame(['heading' => '#abcdef'], $canonical['schema']['sections'][0]['colors']);
    }

    public function test_style_values_are_hex_colors_only(): void
    {
        $colors = app(SectionColors::class);

        $this->assertSame([], $colors->clean(['heading' => 'red; } body { display: none', 'text' => 'url(x)']));
        $this->assertSame('', $colors->style([]));
        $this->assertSame(21.0, Contrast::ratio('#000000', '#ffffff'));
        $this->assertSame(1.0, Contrast::ratio('#365149', '#365149'));
        $this->assertSame('#e7ece8', $colors->background(['background_color' => '#ffffff', 'surface_color' => '#e7ece8', 'primary_color' => '#365149'], 'muted'));
    }

    public function test_invitations_keep_the_template_section_colors(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover', ['type' => 'event', 'colors' => self::COLORS], 'closing']],
        ]);

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->assertEquals(self::COLORS, $invitation->sections()->where('type', 'event')->first()->theme['colors']);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertSee('--inv-section-text: #3d2b1f;', false);
    }
}
