<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use App\Services\Template\TemplateSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Section "heading_align": a section's own title alignment (builder Section panel); "default" keeps the design's.
 */
class SectionHeadingAlignTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'botanical-cream', 'mocha-glass'];

    public function test_sections_keep_the_design_alignment_until_one_is_chosen(): void
    {
        $this->get(route('templates.preview', InvitationTemplate::factory()->create()))->assertOk()->assertDontSee('inv-heading-align--', false);
    }

    public function test_every_design_renders_the_chosen_alignment_on_that_section_only(): void
    {
        foreach (self::DESIGNS as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => ['cover', ['type' => 'event', 'heading_align' => 'left'], 'closing']],
            ]);

            $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'inv-heading-align--left'), "[{$design}]");
            $this->assertMatchesRegularExpression('/data-section="event"[^>]*inv-heading-align--left/s', $html, "[{$design}]");
        }
    }

    public function test_an_unknown_alignment_is_ignored_when_rendering(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => [['type' => 'event', 'heading_align' => '" onload="x']]]]);

        $this->get(route('templates.preview', $template))->assertOk()->assertDontSee('inv-heading-align--', false);
    }

    public function test_the_builder_sets_previews_and_clears_the_alignment(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'event', 'closing']]]);
        $builder = Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template]);
        $event = $builder->get('sections')[1]['key'];

        $builder->call('select', $event)
            ->assertSee('Heading alignment')
            ->call('setHeadingAlign', $event, 'right')
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => $params['patch'] === ['kind' => 'section-class', 'owner' => 'preview-1', 'prefix' => 'inv-heading-align--', 'value' => 'right'])
            ->call('setHeadingAlign', $event, 'diagonal')
            ->assertSet('sections.1.heading_align', 'right')
            ->call('save');

        $this->assertSame('right', $template->fresh()->schema['sections'][1]['heading_align']);

        $builder->call('setHeadingAlign', $event, 'default')->call('save');

        $this->assertArrayNotHasKey('heading_align', $template->fresh()->schema['sections'][1]);
    }

    public function test_the_builder_hides_the_option_where_it_does_not_apply(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'quote']]]);
        $builder = Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template]);

        $builder->call('select', $builder->get('sections')[0]['key'])->assertDontSee('Heading alignment');
        $builder->call('select', $builder->get('sections')[1]['key'])->assertDontSee('Heading alignment');
    }

    public function test_the_schema_rejects_an_unknown_alignment(): void
    {
        $this->expectException(ValidationException::class);

        app(TemplateSchema::class)->validate(['schema' => ['sections' => [['type' => 'event', 'heading_align' => 'diagonal']]]]);
    }

    public function test_invitations_keep_the_template_alignment(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', ['type' => 'event', 'heading_align' => 'left'], 'closing']]]);

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->assertSame('left', $invitation->sections()->where('type', 'event')->first()->theme['heading_align']);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertSee('inv-heading-align--left', false);
    }
}
