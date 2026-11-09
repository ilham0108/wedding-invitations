<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Decoration "scroll_direction": guests scroll down (default) or swipe sideways between sections.
 */
class ScrollDirectionTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'botanical-cream', 'mocha-glass'];

    public function test_invitations_scroll_down_unless_the_template_says_otherwise(): void
    {
        foreach (self::DESIGNS as $design) {
            $html = $this->page($design, []);

            $this->assertStringContainsString('data-inv-scroll="vertical"', $html, "[{$design}]");
            $this->assertStringNotContainsString('data-inv-page-hint', $html, "[{$design}]");
        }
    }

    public function test_every_design_marks_its_page_strip_and_shows_the_hint_when_swiping(): void
    {
        foreach (self::DESIGNS as $design) {
            $html = $this->page($design, ['scroll_direction' => 'horizontal']);

            $this->assertStringContainsString('data-inv-scroll="horizontal"', $html, "[{$design}]");
            $this->assertStringContainsString('data-inv-pages', $html, "[{$design}]");
            $this->assertStringContainsString('data-inv-page-hint', $html, "[{$design}]");
        }
    }

    public function test_an_unknown_direction_falls_back_to_scrolling_down(): void
    {
        $this->assertStringContainsString('data-inv-scroll="vertical"', $this->page('classic', ['scroll_direction' => 'diagonal']));
    }

    public function test_template_cards_stay_a_still_page(): void
    {
        $template = $this->template('classic', ['scroll_direction' => 'horizontal']);

        $html = $this->get(route('templates.card', $template))->assertOk()->getContent();

        $this->assertStringContainsString('data-inv-scroll="vertical"', $html);
        $this->assertStringNotContainsString('data-inv-page-hint', $html);
    }

    public function test_the_builder_sets_the_direction_and_invitations_keep_it(): void
    {
        $template = $this->template('classic', []);

        Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template])
            ->set('panel', 'theme')
            ->assertSee('Sideways')
            ->call('setDecoration', 'scroll_direction', 'horizontal')
            ->assertDispatched('template-preview-refresh')
            ->assertSee('Each section becomes a page.')
            ->call('setDecoration', 'scroll_direction', 'diagonal')
            ->assertSet('decoration.scroll_direction', 'horizontal')
            ->call('save');

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template->fresh(), [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertSee('data-inv-scroll="horizontal"', false);
    }

    /**
     * @param  array<string, string>  $decoration
     */
    private function page(string $design, array $decoration): string
    {
        return $this->get(route('templates.preview', $this->template($design, $decoration)))->assertOk()->getContent();
    }

    /**
     * @param  array<string, string>  $decoration
     */
    private function template(string $design, array $decoration): InvitationTemplate
    {
        return InvitationTemplate::factory()->create([
            'design' => $design,
            'decoration' => $decoration,
            'schema' => ['sections' => ['cover', 'greeting', 'event', 'gallery', 'closing']],
        ]);
    }
}
