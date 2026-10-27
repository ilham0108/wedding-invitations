<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\InvitationRenderer;
use App\Services\Invitation\InvitationService;
use App\Services\Template\TemplateSchema;
use App\Support\CssUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DesignPackTest extends TestCase
{
    use RefreshDatabase;

    private function mochaTemplate(array $attributes = []): InvitationTemplate
    {
        return InvitationTemplate::factory()->create([
            'slug' => 'mocha',
            'design' => 'mocha-glass',
            'schema' => ['sections' => ['cover', 'quote', 'timeline', 'health_protocol', 'closing']],
            'sample_content' => ['cover' => ['title' => 'Arina & Bagas', 'subtitle' => 'The Wedding of']],
            ...$attributes,
        ]);
    }

    public function test_sections_use_design_views_and_fall_back_to_classic_views(): void
    {
        $designs = app(DesignRegistry::class);

        $this->assertSame(['view' => 'designs.mocha-glass.sections.quote', 'native' => true], $designs->sectionView('mocha-glass', 'quote', 'default'));
        $this->assertSame(['view' => 'designs.mocha-glass.sections.health-protocol', 'native' => true], $designs->sectionView('mocha-glass', 'health_protocol', 'default'));
        $this->assertSame(['view' => 'invitation.sections.timeline.default', 'native' => false], $designs->sectionView('mocha-glass', 'timeline', 'default'));
        $this->assertSame(['view' => 'invitation.sections.quote.default', 'native' => true], $designs->sectionView('classic', 'quote', 'default'));
        $this->assertSame('classic', $designs->resolve('does-not-exist'));
        $this->assertNull($designs->pageView('classic'));
        $this->assertSame('designs.mocha-glass.page', $designs->pageView('mocha-glass'));
    }

    public function test_every_configured_design_has_its_assets_in_the_vite_config(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));

        foreach (app(DesignRegistry::class)->all() as $key => $design) {
            foreach ($design['assets'] as $asset) {
                $this->assertFileExists(base_path($asset), "Design [{$key}] asset is missing.");
                $this->assertStringContainsString("'{$asset}'", $vite, "Design [{$key}] asset is not a Vite input.");
            }
        }
    }

    public function test_the_template_schema_validates_the_design(): void
    {
        $this->assertSame('classic', app(TemplateSchema::class)->validate(['schema' => ['sections' => ['cover']]])['design']);
        $this->assertSame('mocha-glass', app(TemplateSchema::class)->validate(['design' => 'mocha-glass', 'schema' => ['sections' => ['cover']]])['design']);

        $this->expectException(ValidationException::class);
        app(TemplateSchema::class)->validate(['design' => '../../etc', 'schema' => ['sections' => ['cover']]]);
    }

    public function test_the_mocha_glass_preview_renders_its_page_frame_and_fallback_sections(): void
    {
        $this->get(route('templates.preview', $this->mochaTemplate()))
            ->assertOk()
            ->assertSee('inv-design-mocha-glass', false)
            ->assertSee('data-mg-cover', false)
            ->assertSee('mg-classic', false)
            ->assertSee('data-section="health_protocol"', false)
            ->assertSee('fonts.bunny.net/css?family=', false)
            ->assertSee('amiri', false);
    }

    public function test_invitations_keep_the_design_they_were_created_with(): void
    {
        $template = $this->mochaTemplate();
        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Arina & Bagas', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $template->update(['design' => 'classic']);

        $rendered = app(InvitationRenderer::class)->forInvitation($invitation->fresh());

        $this->assertSame('mocha-glass', $rendered->design);
        $this->assertSame('designs.mocha-glass.page', $rendered->pageView);
        $this->assertContains('resources/css/designs/mocha-glass.css', $rendered->assets);
    }

    public function test_admins_switch_the_design_in_the_builder(): void
    {
        $template = InvitationTemplate::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template])
            ->call('setDesign', 'not-a-design')
            ->assertSet('design', 'classic')
            ->call('setDesign', 'mocha-glass')
            ->assertSet('hasChanges', true)
            ->call('save');

        $this->assertSame('mocha-glass', $template->fresh()->design);
    }

    public function test_css_urls_cannot_break_out_of_the_url_token(): void
    {
        $this->assertSame('none', CssUrl::wrap(null));
        $this->assertSame('url("/storage/a%20b.webp")', CssUrl::wrap('/storage/a b.webp'));
        $this->assertSame('url("/storage/a%20b.webp")', CssUrl::wrap('/storage/a%20b.webp'));
        $this->assertSame('url("https://x.test/a%27%29;background:red")', CssUrl::wrap("https://x.test/a');background:red"));
        $this->assertSame('url("https://x.test/%22%3E%3Cscript%3E")', CssUrl::wrap('https://x.test/"><script>'));
    }
}
