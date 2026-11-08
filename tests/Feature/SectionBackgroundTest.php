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
 * Section background "plain": the theme's color covers the background photo of an earlier section.
 * "default" stays see-through, so that photo continues behind the section.
 */
class SectionBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private const CLASSES = ['classic' => 'inv-section--plain', 'botanical-cream' => 'bc-bg-plain', 'mocha-glass' => 'mg-bg-plain'];

    public function test_every_design_paints_a_plain_section_and_leaves_the_default_see_through(): void
    {
        foreach (self::CLASSES as $design => $class) {
            $html = $this->page($design, 'plain');
            $this->assertStringContainsString($class, $html, "[{$design}]");

            $this->assertStringNotContainsString($class, $this->page($design, 'default'), "[{$design}]");
        }
    }

    public function test_the_builder_offers_continue_and_plain_and_saves_the_choice(): void
    {
        $template = InvitationTemplate::factory()->create();
        $builder = Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template]);
        $event = $builder->get('sections')[1]['key'];

        $builder->call('select', $event)
            ->assertSee('Continue previous')
            ->assertSee('Plain')
            ->call('setBackground', $event, 'plain')
            ->assertSet('sections.1.background', 'plain')
            ->call('setBackground', $event, 'rainbow')
            ->assertSet('sections.1.background', 'plain')
            ->call('save');

        $this->assertSame('plain', $template->fresh()->schema['sections'][1]['background']);
    }

    public function test_invitations_keep_a_plain_section(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover', ['type' => 'event', 'background' => 'plain']]],
        ]);

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->assertSame('plain', $invitation->sections()->where('type', 'event')->first()->theme['background']);
    }

    private function page(string $design, string $background): string
    {
        $template = InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => ['cover', ['type' => 'event', 'background' => $background], 'closing']],
        ]);

        return $this->get(route('templates.preview', $template))->assertOk()->getContent();
    }
}
