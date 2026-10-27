<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DecorationRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_carries_the_divider_and_texture(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover', 'quote', 'closing']],
            'decoration' => ['divider' => 'flourish', 'texture' => 'linen'],
        ]);

        $this->get(route('templates.preview', $template))
            ->assertOk()
            ->assertSee('data-inv-divider="flourish" data-inv-texture="linen"', false)
            ->assertSee('data-section="closing"', false);
    }

    public function test_unknown_values_fall_back_to_the_defaults(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover']],
            'decoration' => ['divider' => 'zigzag', 'texture' => 'url(evil)'],
        ]);

        $this->get(route('templates.preview', $template))
            ->assertSee('data-inv-divider="line" data-inv-texture="none"', false);
    }

    public function test_admins_choose_the_divider_and_texture_in_the_builder(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover']]]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template])
            ->set('panel', 'theme')
            ->assertSee('Divider')
            ->assertSee('Texture')
            ->call('setDecoration', 'divider', 'dots')
            ->call('setDecoration', 'texture', 'grain')
            ->call('setDecoration', 'texture', 'marble')
            ->assertSet('decoration.texture', 'grain')
            ->call('save');

        $decoration = $template->fresh()->decoration;
        $this->assertSame('dots', $decoration['divider']);
        $this->assertSame('grain', $decoration['texture']);
    }
}
