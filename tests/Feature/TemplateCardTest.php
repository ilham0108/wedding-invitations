<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_card_shows_the_template_cover(): void
    {
        $template = InvitationTemplate::factory()->create([
            'default_configuration' => ['cover' => ['subtitle' => 'The Wedding of']],
            'sample_content' => ['cover' => ['title' => 'Arina & Bagas', 'date_text' => 'Kamis, 31 Desember 2026', 'background_image' => '/storage/templates/cover.webp']],
        ]);

        $this->blade('<x-template-card :template="$template" />', ['template' => $template])
            ->assertSee('/storage/templates/cover.webp', false)
            ->assertSee('Arina &amp; Bagas', false)
            ->assertSee('The Wedding of')
            ->assertSee('Kamis, 31 Desember 2026')
            ->assertSee('fonts.bunny.net', false);
    }

    public function test_an_uploaded_thumbnail_wins_over_the_cover(): void
    {
        $template = InvitationTemplate::factory()->create([
            'thumbnail' => 'templates/thumbnails/custom.webp',
            'sample_content' => ['cover' => ['background_image' => '/storage/templates/cover.webp']],
        ]);

        $this->blade('<x-template-card :template="$template" />', ['template' => $template])
            ->assertSee('templates/thumbnails/custom.webp', false)
            ->assertDontSee('/storage/templates/cover.webp', false);
    }

    public function test_covers_without_a_safe_photo_fall_back_to_the_theme_placeholder(): void
    {
        $template = InvitationTemplate::factory()->create([
            'sample_content' => ['cover' => ['title' => 'Romeo & Juliet', 'background_image' => 'javascript:alert(1)']],
        ]);

        $this->blade('<x-template-card :template="$template" />', ['template' => $template])
            ->assertDontSee('javascript:alert', false)
            ->assertSee('Romeo &amp; Juliet', false)
            ->assertSee('rounded-full border border-black/10', false);
    }
}
