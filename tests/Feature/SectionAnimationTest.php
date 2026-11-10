<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use App\Services\Invitation\SectionAnimations;
use App\Services\Template\TemplateSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Section "animations": the entrance of single elements (builder Section panel, "Animation").
 */
class SectionAnimationTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'botanical-cream', 'mocha-glass'];

    public function test_sections_without_animations_keep_the_block_animation(): void
    {
        $template = InvitationTemplate::factory()->create(['decoration' => ['animation' => 'fade-up']]);

        $this->get(route('templates.preview', $template))->assertOk()
            ->assertDontSee('data-inv-own-anim', false)
            ->assertDontSee('data-inv-anim=', false);
    }

    public function test_every_design_animates_the_elements_of_that_section_only(): void
    {
        foreach (self::DESIGNS as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'decoration' => ['animation' => 'zoom'],
                'schema' => ['sections' => ['cover', ['type' => 'event', 'animations' => ['heading' => ['effect' => 'fade-down', 'delay' => 3]]], 'closing']],
                'sample_content' => ['event' => ['heading' => 'Acara', 'events' => [['title' => 'Akad'], ['title' => 'Resepsi']]]],
            ]);

            $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'data-inv-own-anim'), "[{$design}]");
            $this->assertMatchesRegularExpression('/data-section="event"[^>]*data-inv-own-anim/s', $html, "[{$design}]");
            $this->assertStringContainsString('data-inv-anim="fade-down" data-inv-delay="3"', $html, "[{$design}]");
            // Events follow the theme ("zoom"), one after the other.
            $this->assertStringContainsString('data-inv-anim="zoom-in"', $html, "[{$design}]");
            $this->assertStringContainsString('data-inv-anim="zoom-in" data-inv-delay="1"', $html, "[{$design}]");
        }
    }

    public function test_an_element_on_no_entrance_gets_no_attributes(): void
    {
        $template = InvitationTemplate::factory()->create([
            'decoration' => ['animation' => 'none'],
            'schema' => ['sections' => [['type' => 'closing', 'animations' => ['heading' => ['effect' => 'none', 'delay' => 0], 'message' => ['effect' => 'fade', 'delay' => 0]]]]],
            'sample_content' => ['closing' => ['heading' => 'Terima kasih', 'message' => 'Sampai jumpa']],
        ]);

        $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-inv-anim="'));
        $this->assertStringContainsString('data-inv-anim="fade"', $html);
    }

    public function test_attributes_follow_the_theme_and_stagger_list_items(): void
    {
        $animations = app(SectionAnimations::class);
        $section = ['type' => 'event', 'animations' => ['events' => ['effect' => 'default', 'delay' => 9]]];

        $this->assertSame(' data-inv-anim="fade-right" data-inv-delay="9"', $animations->attributes($section, 'slide', 'events'));
        $this->assertSame(' data-inv-anim="fade-left" data-inv-delay="10"', $animations->attributes($section, 'slide', 'events', 1));
        $this->assertSame(' data-inv-anim="fade-right" data-inv-delay="14"', $animations->attributes($section, 'slide', 'events', 8));
        $this->assertSame('', $animations->attributes($section, 'none', 'heading'));
        $this->assertSame('', $animations->attributes($section, 'fade', 'unknown'));
        $this->assertSame('', $animations->attributes(['type' => 'event', 'animations' => []], 'fade', 'heading'));
    }

    public function test_cleaning_keeps_known_elements_and_drops_defaults(): void
    {
        $clean = app(SectionAnimations::class)->clean('event', [
            'heading' => ['effect' => 'zoom-in', 'delay' => 40],
            'events' => ['effect' => 'default', 'delay' => 0],
            'title' => ['effect' => 'fade'],
            'notes' => 'fade',
        ]);

        $this->assertSame(['heading' => ['effect' => 'zoom-in', 'delay' => 10]], $clean);
    }

    public function test_a_zigzag_list_alternates_sides_starting_from_the_chosen_one(): void
    {
        $animations = app(SectionAnimations::class);
        $story = fn (string $effect): array => ['type' => 'story', 'animations' => ['entries' => ['effect' => $effect, 'delay' => 0, 'pattern' => 'zigzag']]];
        $sides = fn (string $effect, ?string $theme = null): array => array_map(
            fn (int $index): string => $animations->attributes($story($effect), $theme, 'entries', $index),
            [0, 1, 2],
        );

        $this->assertSame([' data-inv-anim="fade-right"', ' data-inv-anim="fade-left" data-inv-delay="1"', ' data-inv-anim="fade-right" data-inv-delay="2"'], $sides('fade-up'));
        $this->assertSame([' data-inv-anim="fade-left"', ' data-inv-anim="fade-right" data-inv-delay="1"', ' data-inv-anim="fade-left" data-inv-delay="2"'], $sides('fade-left'));
        $this->assertSame([' data-inv-anim="zoom-in-right"', ' data-inv-anim="zoom-in-left" data-inv-delay="1"', ' data-inv-anim="zoom-in-right" data-inv-delay="2"'], $sides('default', 'zoom'));
        $this->assertSame(['', '', ''], $sides('none'));
    }

    public function test_only_lists_keep_a_pattern(): void
    {
        $animations = app(SectionAnimations::class);

        $this->assertSame(['entries' => ['effect' => 'default', 'delay' => 0, 'pattern' => 'zigzag']], $animations->clean('story', ['entries' => ['pattern' => 'zigzag']]));
        $this->assertSame([], $animations->clean('story', ['heading' => ['pattern' => 'zigzag'], 'entries' => ['pattern' => 'spiral']]));
    }

    public function test_the_builder_sets_a_zigzag_story_and_the_page_renders_it(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover', 'story']],
            'sample_content' => ['story' => ['heading' => 'Kisah', 'entries' => [['title' => 'Bertemu'], ['title' => 'Lamaran'], ['title' => 'Menikah']]]],
        ]);
        $builder = Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template]);
        $story = $builder->get('sections')[1]['key'];

        $builder->call('select', $story)
            ->assertSee('Item order')
            ->call('setAnimation', $story, 'entries', 'pattern', 'zigzag')
            ->call('setAnimation', $story, 'entries', 'pattern', 'spiral')
            ->assertSet('sections.1.animations', ['entries' => ['effect' => 'default', 'delay' => 0, 'pattern' => 'zigzag']])
            ->call('setAnimation', $story, 'entries', 'effect', 'fade-up')
            ->call('save');

        $html = $this->get(route('templates.preview', $template->fresh()))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-inv-anim="fade-right"[^>]*>.*data-inv-anim="fade-left" data-inv-delay="1".*data-inv-anim="fade-right" data-inv-delay="2"/s', $html);
    }

    public function test_the_builder_sets_and_resets_element_animations(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'event', 'closing']]]);
        $builder = Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template]);
        $event = $builder->get('sections')[1]['key'];

        $builder->call('select', $event)
            ->assertSee('Events (each event)')
            ->call('setAnimation', $event, 'heading', 'effect', 'fade-up')
            ->assertDispatched('template-preview-refresh')
            ->call('setAnimation', $event, 'heading', 'delay', '4')
            ->call('setAnimation', $event, 'heading', 'effect', 'explode')
            ->call('setAnimation', $event, 'venue', 'effect', 'fade')
            ->call('setAnimation', $event, 'heading', 'onclick', 'x')
            ->assertSet('sections.1.animations', ['heading' => ['effect' => 'fade-up', 'delay' => 4]])
            ->assertSee('Back to the design animation')
            ->call('save');

        $this->assertEquals(['heading' => ['effect' => 'fade-up', 'delay' => 4]], $template->fresh()->schema['sections'][1]['animations']);

        $builder->call('resetAnimations', $event)->call('save');

        $this->assertArrayNotHasKey('animations', $template->fresh()->schema['sections'][1]);
    }

    public function test_the_schema_rejects_an_unknown_effect(): void
    {
        $this->expectException(ValidationException::class);

        app(TemplateSchema::class)->validate(['schema' => ['sections' => [['type' => 'event', 'animations' => ['heading' => ['effect' => 'explode']]]]]]);
    }

    public function test_invitations_keep_the_template_animations(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', ['type' => 'closing', 'animations' => ['heading' => ['effect' => 'zoom-out', 'delay' => 2]]]]]]);

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->assertEquals(['heading' => ['effect' => 'zoom-out', 'delay' => 2]], $invitation->sections()->where('type', 'closing')->first()->theme['animations']);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertSee('data-inv-anim="zoom-out" data-inv-delay="2"', false);
    }
}
