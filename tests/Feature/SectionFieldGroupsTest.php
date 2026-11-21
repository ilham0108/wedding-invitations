<?php

namespace Tests\Feature;

use App\Livewire\SectionSettings;
use App\Livewire\TemplateSectionContent;
use App\Models\InvitationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

/**
 * Section settings are grouped into collapsible parts (config invitation.field_groups), and the cover's own
 * text color wins over the white text a dark overlay would give it.
 */
class SectionFieldGroupsTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    public function test_the_builder_shows_the_cover_fields_in_groups(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover', 'event']]]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateSectionContent::class, ['templateUlid' => $template->ulid, 'type' => 'cover', 'group' => 'default_configuration'])
            ->assertSeeInOrder(['Names &amp; text', 'Couple photo', 'Photo style', 'Background', 'Layout &amp; position', 'Guest greeting', 'Open button'], false)
            // The classic design has no desktop side panel unless its desktop layout is "split".
            ->assertDontSee('Desktop side panel')
            // The first part starts open.
            ->assertSeeHtml("open: { 'text': true }")
            // The four fade toggles sit side by side.
            ->assertSeeHtml('<div class="grid gap-x-4 gap-y-3 sm:grid-cols-2">');
    }

    public function test_clients_see_groups_without_template_only_fields(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'event']);

        Livewire::actingAs($invitation->user)
            ->test(SectionSettings::class, ['invitationUlid' => $invitation->ulid, 'sectionUlid' => $invitation->sections()->where('type', 'cover')->value('ulid')])
            ->assertSee('Guest greeting')
            ->assertSee('Greet the guest above the button')
            ->assertDontSee('Title position (vertical)');
    }

    public function test_other_sections_split_content_from_background(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'event']);

        Livewire::actingAs($invitation->user)
            ->test(SectionSettings::class, ['invitationUlid' => $invitation->ulid, 'sectionUlid' => $invitation->sections()->where('type', 'event')->value('ulid')])
            ->assertSeeInOrder(['Content', 'Background'])
            ->assertSeeHtml("open: { 'content': true }");
    }

    public function test_the_cover_text_color_wins_over_the_overlay_white(): void
    {
        $page = function (array $colors): string {
            $template = InvitationTemplate::factory()->create([
                'schema' => ['sections' => [['type' => 'cover', 'colors' => $colors], 'event']],
                'default_configuration' => ['cover' => ['background_image' => '/images/cover.jpg', 'overlay' => true, 'button_position' => 'bottom']],
                'sample_content' => ['cover' => ['background_image' => '/images/cover.jpg']],
            ]);

            return $this->get(route('templates.preview', $template))->assertOk()->getContent();
        };

        $this->assertMatchesRegularExpression('/inv-cover__content[^"]*text-white/', $page([]));
        $this->assertStringContainsString('<div class="inv-cover-bar text-white">', $page([]));

        // The overlay also dims the cover's ornaments.
        $this->assertMatchesRegularExpression('/<section id="section-preview-0" data-section="cover"[^>]*data-inv-dim-ornaments/', $page([]));

        $html = $page(['text' => '#f3e2b8']);
        $this->assertDoesNotMatchRegularExpression('/inv-cover__content[^"]*text-white/', $html);
        $this->assertStringContainsString('<div class="inv-cover-bar">', $html);
        $this->assertStringContainsString('--inv-section-text: #f3e2b8', $html);
    }
}
