<?php

namespace Tests\Feature;

use App\Livewire\TemplateBuilder;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationAnimationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_choose_the_animation_and_its_speed_in_the_builder(): void
    {
        $template = InvitationTemplate::factory()->create(['decoration' => []]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TemplateBuilder::class, ['template' => $template])
            ->assertSet('decoration.animation', 'none')
            ->call('setDecoration', 'animation', 'slide')
            ->call('setDecoration', 'animation_speed', 'slow')
            ->call('setDecoration', 'animation', 'explode')
            ->call('setDecoration', 'onclick', 'alert(1)')
            ->assertSet('decoration.animation', 'slide')
            ->assertSet('hasChanges', true)
            ->call('save');

        $this->assertSame(['animation' => 'slide', 'animation_speed' => 'slow'], $template->fresh()->decoration);
    }

    public function test_the_page_carries_the_animation_and_marks_sections(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover', 'event', 'closing']],
            'decoration' => ['animation' => 'zoom', 'animation_speed' => 'fast'],
        ]);

        $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

        $this->assertStringContainsString('data-inv-animation="zoom" data-inv-speed="fast"', $html);
        $this->assertStringContainsString("document.documentElement.classList.add('inv-js')", $html);
        $this->assertMatchesRegularExpression('/data-section="event"\s+class="[^"]*inv-animate/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-section="cover"\s+class="[^"]*inv-animate/', $html);
    }

    public function test_invitations_keep_the_animation_they_were_created_with(): void
    {
        $template = InvitationTemplate::factory()->create(['decoration' => ['animation' => 'fade-up']]);
        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'John & Jane', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $template->update(['decoration' => ['animation' => 'none']]);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertOk()
            ->assertSee('data-inv-animation="fade-up" data-inv-speed="normal"', false);
    }
}
