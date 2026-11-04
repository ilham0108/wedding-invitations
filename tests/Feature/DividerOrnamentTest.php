<?php

namespace Tests\Feature;

use App\Enums\IllustrationCategory;
use App\Livewire\TemplateBuilder;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\DecorationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Decoration divider "image": a library ornament used for the divider marks of every design.
 */
class DividerOrnamentTest extends TestCase
{
    use RefreshDatabase;

    private function ornament(IllustrationCategory $category = IllustrationCategory::Ornament): Illustration
    {
        return Illustration::query()->create([
            'category' => $category, 'name' => 'Batik', 'disk' => null, 'path' => 'illustrations/ornament-flower-corner.svg',
            'width' => 400, 'height' => 120, 'is_premium' => false, 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function builder(InvitationTemplate $template)
    {
        return Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateBuilder::class, ['template' => $template]);
    }

    public function test_the_divider_image_is_picked_from_the_ornament_library_only(): void
    {
        $ornament = $this->ornament();
        $person = Illustration::query()->create([
            'category' => IllustrationCategory::Person, 'name' => 'Orang', 'disk' => null, 'path' => 'illustrations/person-bride.svg',
            'width' => 600, 'height' => 600, 'is_premium' => false, 'is_active' => true, 'sort_order' => 1,
        ]);
        $template = InvitationTemplate::factory()->create();

        $this->builder($template)
            // "image" cannot be set without a library pick, nor can an arbitrary image key.
            ->call('setDecoration', 'divider', 'image')->assertSet('decoration.divider', 'line')
            ->call('setDecoration', 'divider_image', $ornament->ulid)->assertSet('decoration.divider_image', '')
            ->call('chooseDividerImage', $person->ulid)->assertNotFound();

        $this->builder($template)
            ->set('panel', 'theme')->set('dividerPicker', true)->assertSee($ornament->url(), false)
            ->call('chooseDividerImage', $ornament->ulid)
            ->assertSet('decoration.divider', 'image')->assertSet('dividerPicker', false)
            ->call('setDecoration', 'divider_size', 'large')
            ->assertDispatched('template-preview-patch', fn (string $name, array $params): bool => $params['patch'] === ['kind' => 'attributes', 'attributes' => ['data-inv-divider-size' => 'large']])
            ->call('save');

        $this->assertSame(['image', $ornament->ulid, 'large'], [$template->fresh()->decoration['divider'], $template->fresh()->decoration['divider_image'], $template->fresh()->decoration['divider_size']]);

        $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();
        $this->assertStringContainsString('data-inv-divider="image"', $html);
        $this->assertStringContainsString('data-inv-divider-size="large"', $html);
        $this->assertStringContainsString('--inv-divider-image: url(', $html);
        $this->assertStringContainsString($ornament->url(), $html);
    }

    public function test_botanical_cream_swaps_its_own_marks_for_the_image_or_drops_them(): void
    {
        $ornament = $this->ornament();
        $template = InvitationTemplate::factory()->create(['design' => 'botanical-cream', 'schema' => ['sections' => ['cover', 'closing']]]);

        $this->assertStringContainsString('class="bc-diamond"', $this->get(route('templates.preview', $template))->getContent());

        $template->update(['decoration' => ['divider' => 'image', 'divider_image' => $ornament->ulid]]);
        $html = $this->get(route('templates.preview', $template))->getContent();
        $this->assertStringContainsString('class="inv-divider-image', $html);
        $this->assertStringNotContainsString('class="bc-diamond"', $html);

        $template->update(['decoration' => ['divider' => 'none']]);
        $html = $this->get(route('templates.preview', $template))->getContent();
        $this->assertStringNotContainsString('class="bc-diamond"', $html);
        $this->assertStringNotContainsString('inv-divider-image', $html);
    }

    public function test_a_missing_or_hidden_divider_image_falls_back_to_the_design_divider(): void
    {
        $ornament = $this->ornament();
        $resolver = app(DecorationResolver::class);

        $this->assertSame('image', $resolver->forPage(['divider' => 'image', 'divider_image' => $ornament->ulid])['divider']);

        $ornament->update(['is_active' => false]);
        $page = $resolver->forPage(['divider' => 'image', 'divider_image' => $ornament->ulid]);
        $this->assertSame(['line', ''], [$page['divider'], $page['divider_url']]);

        // Only ULIDs are accepted as divider images, never URLs.
        $this->assertSame('', $resolver->resolve(['divider_image' => 'https://evil.test/x.png'])['divider_image']);
    }
}
