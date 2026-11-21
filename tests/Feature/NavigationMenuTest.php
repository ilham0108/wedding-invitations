<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\DecorationResolver;
use App\Services\Invitation\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The floating section menu (decoration "nav_display" / "nav_items", NavigationMenu), shared by every design.
 */
class NavigationMenuTest extends TestCase
{
    use RefreshDatabase;

    private const SECTIONS = ['cover', 'couple', 'event', 'gallery', 'wishes', 'closing'];

    public function test_each_design_starts_with_its_own_default(): void
    {
        $this->assertStringNotContainsString('data-inv-nav', $this->page('classic'));

        foreach (['mocha-glass', 'botanical-cream'] as $design) {
            $this->assertStringContainsString('data-inv-nav', $this->page($design), "[{$design}]");
        }
    }

    public function test_the_template_can_show_or_hide_it_in_every_design(): void
    {
        foreach (['classic', 'mocha-glass', 'botanical-cream'] as $design) {
            $shown = $this->page($design, ['nav_display' => 'show']);

            $this->assertStringContainsString('data-inv-nav', $shown, "[{$design}]");
            // Hidden while the cover, the first section, is on screen.
            $this->assertMatchesRegularExpression('/<nav class="inv-nav is-hidden" data-inv-nav aria-label="[^"]+"\s+inert\s*>/', $shown, "[{$design}]");
            $this->assertStringNotContainsString('data-inv-nav', $this->page($design, ['nav_display' => 'hide']), "[{$design}]");
        }
    }

    public function test_automatic_entries_follow_the_page_and_link_to_its_sections(): void
    {
        $html = $this->page('classic', ['nav_display' => 'show']);

        $this->assertSeeInNav($html, ['Home', 'Couple', 'Event', 'Gallery', 'Wishes']);
        $this->assertStringContainsString('href="#section-preview-1" class="inv-nav__link"', $html);
        $this->assertStringContainsString('fa-solid fa-user-group', $html);
    }

    public function test_own_entries_set_order_label_and_icon_and_skip_missing_sections(): void
    {
        $html = $this->page('classic', ['nav_display' => 'show', 'nav_items' => [
            ['type' => 'event', 'label' => 'Jadwal', 'icon' => 'clock'],
            ['type' => 'rsvp', 'label' => '', 'icon' => 'check'],
            ['type' => 'cover', 'label' => '', 'icon' => 'ring'],
        ]]);

        $this->assertSeeInNav($html, ['Jadwal', 'Home']);
        $this->assertStringContainsString('fa-regular fa-clock', $html);
        $this->assertStringContainsString('fa-solid fa-ring', $html);
        // The template has no RSVP section, so that entry is left out.
        $this->assertSame(2, substr_count($html, 'data-inv-nav-link'));
    }

    public function test_a_menu_with_one_entry_is_not_shown(): void
    {
        $html = $this->page('classic', ['nav_display' => 'show', 'nav_items' => [['type' => 'event', 'label' => '', 'icon' => 'calendar']]]);

        $this->assertStringNotContainsString('data-inv-nav', $html);
    }

    public function test_stored_entries_are_cleaned(): void
    {
        $items = app(DecorationResolver::class)->navItems([
            ['type' => 'event', 'label' => str_repeat('a', 40), 'icon' => 'fa-brands fa-evil'],
            ['type' => 'event', 'label' => 'Twice', 'icon' => 'clock'],
            ['type' => 'nope', 'label' => 'Unknown', 'icon' => 'clock'],
            'not an entry',
            ['type' => 'cover', 'label' => '  Beranda   kita ', 'icon' => 'house'],
            ['type' => 'couple'], ['type' => 'gallery'], ['type' => 'wishes'], ['type' => 'closing'],
        ]);

        $this->assertCount(5, $items);
        $this->assertSame(['type' => 'event', 'label' => str_repeat('a', 16), 'icon' => 'calendar'], $items[0]);
        $this->assertSame(['type' => 'cover', 'label' => 'Beranda kita', 'icon' => 'house'], $items[1]);
        $this->assertSame(['event', 'cover', 'couple', 'gallery', 'wishes'], array_column($items, 'type'));
        $this->assertSame([], app(DecorationResolver::class)->navItems('cover'));
    }

    public function test_template_cards_have_no_menu(): void
    {
        $template = $this->template('mocha-glass');

        $this->assertStringNotContainsString('data-inv-nav', $this->get(route('templates.card', $template))->assertOk()->getContent());
    }

    public function test_the_builder_edits_the_menu_and_new_invitations_keep_it(): void
    {
        $template = $this->template('classic');

        $admin = User::factory()->superAdmin()->create();
        $builder = Livewire::actingAs($admin)->test(TemplateBuilder::class, ['template' => $template])
            ->set('panel', 'theme')
            ->assertDontSee('Edit menu items')
            ->call('setDecoration', 'nav_display', 'show')
            ->assertSet('decoration.nav_display', 'show')
            ->assertSee('Edit menu items')
            ->call('customizeNav')
            ->assertDispatched('template-preview-refresh');

        $this->assertSame(['cover', 'couple', 'event', 'gallery', 'wishes'], array_column($builder->get('decoration')['nav_items'], 'type'));

        $builder
            // Five entries at most.
            ->call('addNavItem')
            ->assertCount('decoration.nav_items', 5)
            ->call('removeNavItem', 0)
            ->call('addNavItem')
            ->assertSet('decoration.nav_items.4.type', 'cover')
            ->call('updateNavItem', 0, 'label', 'Pasangan')
            ->call('updateNavItem', 0, 'icon', 'heart')
            ->call('updateNavItem', 0, 'icon', 'not-an-icon')
            // A section already listed cannot be picked twice.
            ->call('updateNavItem', 1, 'type', 'couple')
            ->assertSet('decoration.nav_items.1.type', 'event')
            // The icon follows a new section while it is still the old section's default.
            ->call('updateNavItem', 1, 'type', 'closing')
            ->assertSet('decoration.nav_items.1.icon', 'envelope')
            ->call('moveNavItem', 1, -1)
            ->assertSet('decoration.nav_items.0.type', 'closing')
            ->assertSet('decoration.nav_items.1', ['type' => 'couple', 'label' => 'Pasangan', 'icon' => 'heart'])
            ->call('save');

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template->fresh(), [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $html = $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertSeeInNav($html, ['Closing', 'Pasangan', 'Gallery', 'Wishes', 'Home']);
        $this->assertStringContainsString('href="#section-'.$invitation->sections()->where('type', 'couple')->value('ulid').'"', $html);

        // Back to the automatic entries.
        Livewire::actingAs($admin)->test(TemplateBuilder::class, ['template' => $template->fresh()])
            ->call('resetNavItems')
            ->assertSet('decoration.nav_items', []);
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function assertSeeInNav(string $html, array $labels): void
    {
        preg_match('#<nav class="inv-nav[^"]*" data-inv-nav.*?</nav>#s', $html, $nav);
        $this->assertNotEmpty($nav, 'The page has no floating menu.');
        preg_match_all('#<span class="inv-nav__label">(.*?)</span>#', $nav[0], $found);
        $this->assertSame($labels, $found[1]);
    }

    /**
     * @param  array<string, mixed>  $decoration
     */
    private function page(string $design, array $decoration = []): string
    {
        return $this->get(route('templates.preview', $this->template($design, $decoration)))->assertOk()->getContent();
    }

    /**
     * @param  array<string, mixed>  $decoration
     */
    private function template(string $design, array $decoration = []): InvitationTemplate
    {
        return InvitationTemplate::factory()->create([
            'design' => $design,
            'decoration' => $decoration,
            'schema' => ['sections' => self::SECTIONS],
        ]);
    }
}
