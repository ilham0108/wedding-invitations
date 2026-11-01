<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\FontRegistry;
use App\Services\Invitation\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Theme heading_weight / body_weight: "default" keeps each design's weights; a number overrides them.
 */
class ThemeFontWeightTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_themes_keep_the_design_weights(): void
    {
        $theme = app(ThemeResolver::class)->resolve([]);

        $this->assertSame(['default', 'default'], [$theme['heading_weight'], $theme['body_weight']]);
        $this->assertStringNotContainsString('weight', app(ThemeResolver::class)->cssVariables($theme));

        $html = $this->get(route('templates.preview', InvitationTemplate::factory()->create()))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-inv-heading-weight', $html);
        $this->assertStringNotContainsString('data-inv-body-weight', $html);
    }

    public function test_the_builder_sets_weights_live_and_the_invitation_renders_them(): void
    {
        $template = InvitationTemplate::factory()->create(['theme' => ['font_heading' => 'Lora', 'font_body' => 'DM Sans']]);

        Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template])
            ->set('panel', 'theme')
            // Only the weights the fonts load are offered (Lora and DM Sans: 400, 500, 600).
            ->assertSeeHtml('<option value="600">')->assertDontSeeHtml('<option value="700">')
            ->set('theme.heading_weight', '650')->assertHasErrors('theme.heading_weight')
            ->set('theme.heading_weight', '600')->assertHasNoErrors()
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => $params['patch']['kind'] === 'theme'
                && str_contains($params['patch']['css'], '--inv-heading-weight: 600;')
                && $params['patch']['attributes'] === ['data-inv-heading-weight' => '600', 'data-inv-body-weight' => null])
            ->assertNotDispatched('template-preview-refresh')
            ->call('save');

        $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();
        $this->assertStringContainsString('data-inv-heading-weight="600"', $html);
        $this->assertStringContainsString('--inv-heading-weight: 600;', $html);
        $this->assertStringNotContainsString('data-inv-body-weight', $html);
    }

    public function test_weight_choices_follow_the_fonts_loaded_weights(): void
    {
        $fonts = app(FontRegistry::class);

        $this->assertSame([400, 500, 600], $fonts->weights('Lora'));
        $this->assertSame([400, 700], $fonts->weights('Cinzel Decorative'));
        $this->assertSame([], $fonts->weights('Nope'));

        // The current value stays selectable after switching to a font without it.
        $this->assertSame(['400', '500', '600', '700'], $fonts->themeWeights(['font_heading' => 'Lora', 'heading_weight' => '700', 'font_body' => 'Lora', 'body_weight' => 'default'])['heading_weight']);
    }
}
