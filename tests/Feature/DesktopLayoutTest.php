<?php

namespace Tests\Feature;

use App\Livewire\SectionSettings;
use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\DesignRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class DesktopLayoutTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function template(array $decoration = []): InvitationTemplate
    {
        return InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover', 'quote']],
            'decoration' => $decoration,
            'sample_content' => ['cover' => [
                'title' => 'Romeo & Juliet',
                'background_image' => '/illustrations/cover-photo.jpg',
                'desktop_title' => 'Save the date',
                'desktop_text' => 'We are getting married',
            ]],
        ]);
    }

    public function test_classic_pages_default_to_the_centered_column(): void
    {
        $this->get(route('templates.preview', $this->template()))
            ->assertOk()
            ->assertSee('inv-page inv-page--column', false)
            ->assertSee('inv-aside inv-aside--photo', false)
            ->assertSee('/illustrations/cover-photo.jpg', false)
            ->assertDontSee('Save the date');
    }

    public function test_the_side_panel_shows_the_desktop_cover_content(): void
    {
        $this->get(route('templates.preview', $this->template(['desktop_layout' => 'split'])))
            ->assertOk()
            ->assertSee('inv-page inv-page--split', false)
            ->assertSee('inv-aside--text-center', false)
            ->assertSee('Save the date')
            ->assertSee('We are getting married');
    }

    public function test_full_width_has_no_desktop_backdrop(): void
    {
        $this->get(route('templates.preview', $this->template(['desktop_layout' => 'full'])))
            ->assertOk()
            ->assertSee('inv-page inv-page--full', false)
            ->assertDontSee('<aside', false);
    }

    public function test_the_side_panel_unlocks_desktop_panel_fields_only_for_designs_without_a_page_frame(): void
    {
        $designs = app(DesignRegistry::class);

        $this->assertSame([], $designs->features('classic', ['desktop_layout' => 'column']));
        $this->assertSame(['desktop_panel'], $designs->features('classic', ['desktop_layout' => 'split']));
        $this->assertSame(['desktop_panel'], $designs->features('mocha-glass', ['desktop_layout' => 'full']));
        $this->assertTrue($designs->hasDesktopLayouts('classic'));
        $this->assertFalse($designs->hasDesktopLayouts('mocha-glass'));
    }

    public function test_admins_choose_the_desktop_layout_in_the_builder(): void
    {
        $template = $this->template();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template])
            ->set('panel', 'theme')
            ->assertSee('Desktop layout')
            ->assertSet('decoration.desktop_layout', 'column')
            ->call('setDecoration', 'desktop_layout', 'sideways')
            ->assertSet('decoration.desktop_layout', 'column')
            ->call('setDecoration', 'desktop_layout', 'split')
            ->assertSee('Set the panel text in the Cover section')
            ->call('save');

        $this->assertSame('split', $template->fresh()->decoration['desktop_layout']);
    }

    public function test_designs_with_their_own_page_frame_hide_the_desktop_layout_choice(): void
    {
        $template = InvitationTemplate::factory()->create(['design' => 'mocha-glass']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template])
            ->set('panel', 'theme')
            ->assertDontSee('Desktop layout');
    }

    public function test_clients_cannot_see_or_change_the_desktop_panel_content(): void
    {
        $invitation = $this->createInvitation(sections: ['cover', 'quote']);
        $invitation->settings()->first()->update(['decoration' => ['desktop_layout' => 'split']]);
        $cover = $invitation->sections()->where('type', 'cover')->first();
        $cover->update(['configuration' => [...$cover->configuration, 'desktop_title' => 'From the template']]);

        Livewire::actingAs($invitation->user)
            ->test(SectionSettings::class, ['invitationUlid' => $invitation->ulid, 'sectionUlid' => $cover->ulid])
            ->assertDontSee('Desktop panel: title')
            ->set('config.desktop_title', 'Hacked')
            ->set('config.title', 'John & Jane')
            ->assertHasNoErrors();

        $saved = $cover->fresh()->configuration;
        $this->assertSame('From the template', $saved['desktop_title']);
        $this->assertSame('John & Jane', $saved['title']);
    }
}
