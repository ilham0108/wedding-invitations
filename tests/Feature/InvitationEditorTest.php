<?php

namespace Tests\Feature;

use App\Livewire\InvitationEditor;
use App\Livewire\SectionSettings;
use App\Models\User;
use App\Services\Invitation\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

class InvitationEditorTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, RefreshDatabase;

    public function test_owner_can_open_the_editor(): void
    {
        $invitation = $this->createInvitation();

        $this->actingAs($invitation->user)
            ->get('/dashboard/invitations/'.$invitation->ulid.'/edit')
            ->assertOk()
            ->assertSeeLivewire(InvitationEditor::class)
            ->assertSee('Live preview');
    }

    public function test_other_users_cannot_open_the_editor(): void
    {
        $invitation = $this->createInvitation();

        $this->actingAs(User::factory()->client()->create())
            ->get('/dashboard/invitations/'.$invitation->ulid.'/edit')
            ->assertForbidden();
    }

    public function test_editor_state_never_contains_the_numeric_invitation_id(): void
    {
        $invitation = $this->createInvitation();

        $component = Livewire::actingAs($invitation->user)->test(InvitationEditor::class, ['invitation' => $invitation]);

        $this->assertSame($invitation->ulid, $component->get('invitationUlid'));
        $this->assertStringNotContainsString('"id":'.$invitation->id, json_encode($component->snapshot));
    }

    public function test_locked_invitation_ulid_cannot_be_tampered_with(): void
    {
        $invitation = $this->createInvitation();
        $foreign = $this->createInvitation();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->set('invitationUlid', $foreign->ulid);
    }

    public function test_theme_changes_are_validated_and_saved(): void
    {
        $invitation = $this->createInvitation();
        $this->subscribe($invitation->user, ['theme_customization' => true]);

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->set('theme.primary_color', '#112233')
            ->assertHasNoErrors()
            ->assertDispatched('invitation-updated')
            ->set('theme.font_heading', 'Comic Sans From The Internet')
            ->assertHasErrors('theme.font_heading');

        $theme = $invitation->settings()->first()->theme;

        $this->assertSame('#112233', $theme['primary_color']);
        $this->assertNotSame('Comic Sans From The Internet', $theme['font_heading']);
    }

    public function test_applying_a_preset_replaces_the_theme(): void
    {
        $invitation = $this->createInvitation();
        $this->subscribe($invitation->user, ['theme_customization' => true]);

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->call('applyPreset', 'luxury')
            ->assertSet('theme.font_heading', 'Cinzel');

        $this->assertSame('luxury', $invitation->settings()->first()->theme['preset']);
    }

    public function test_settings_are_saved(): void
    {
        $invitation = $this->createInvitation();

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->set('settings.title', 'John & Jane Forever')
            ->set('settings.allow_search_indexing', true)
            ->set('settings.timezone', 'Asia/Makassar')
            ->assertHasNoErrors()
            ->set('settings.title', '')
            ->assertHasErrors('settings.title');

        $invitation->refresh();

        $this->assertSame('John & Jane Forever', $invitation->title);
        $this->assertSame('Asia/Makassar', $invitation->timezone);
        $this->assertTrue($invitation->settings()->first()->allow_search_indexing);
    }

    public function test_theme_and_settings_panels_render(): void
    {
        $invitation = $this->createInvitation();
        $this->subscribe($invitation->user, ['theme_customization' => true]);

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->set('panel', 'theme')
            ->assertSee('Presets')
            ->assertSee('Heading font')
            ->set('panel', 'settings')
            ->assertSee('Allow search engines');
    }

    public function test_plans_without_theme_customization_hide_the_theme_panel_and_reject_theme_changes(): void
    {
        $this->freePlan(['theme_customization' => false]);
        $invitation = $this->createInvitation();
        $before = $invitation->settings()->first()->theme;

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->assertDontSee('Presets')
            ->set('panel', 'theme')
            ->assertSet('panel', 'section')
            ->assertDontSee('Heading font');

        // A forbidden response ends the component, so each attempt gets a fresh instance.
        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->set('theme.primary_color', '#112233')
            ->assertForbidden();

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->call('applyPreset', 'luxury')
            ->assertForbidden();

        $this->assertSame($before, $invitation->settings()->first()->theme);
    }

    public function test_plans_with_theme_customization_show_the_theme_panel(): void
    {
        $this->freePlan(['theme_customization' => false]);
        $invitation = $this->createInvitation();
        $this->subscribe($invitation->user, ['theme_customization' => true]);

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->set('panel', 'theme')
            ->assertSee('Heading font')
            ->set('theme.primary_color', '#112233')
            ->assertHasNoErrors();

        $this->assertSame('#112233', $invitation->settings()->first()->theme['primary_color']);
    }

    public function test_property_editor_renders_every_section_type_with_items(): void
    {
        $types = app(SectionRegistry::class)->types();
        $invitation = $this->createInvitation(sections: $types);

        foreach ($invitation->sections as $section) {
            $component = Livewire::actingAs($invitation->user)->test(SectionSettings::class, [
                'invitationUlid' => $invitation->ulid,
                'sectionUlid' => $section->ulid,
            ])->assertOk();

            foreach (app(SectionRegistry::class)->fields($section->type) as $field) {
                match ($field['type']) {
                    'repeater' => $component->call('addItem', $field['key']),
                    'colors' => $component->call('addColor', $field['key']),
                    default => null,
                };
            }

            $component->assertOk()->assertHasNoErrors();
        }

        $this->assertCount(count($types), $invitation->sections);
    }

    public function test_embedded_preview_has_no_preview_banner(): void
    {
        $invitation = $this->createInvitation();

        $this->actingAs($invitation->user)
            ->get('/dashboard/invitations/'.$invitation->ulid.'/preview?embed=1')
            ->assertOk()
            ->assertDontSee('Back to editor');
    }
}
