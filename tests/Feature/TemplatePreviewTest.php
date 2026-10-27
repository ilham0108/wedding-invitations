<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use Database\Seeders\InvitationTemplateSeeder;
use Database\Seeders\TemplateCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_seeded_template_renders_its_preview(): void
    {
        $this->seed([TemplateCategorySeeder::class, InvitationTemplateSeeder::class]);

        $templates = InvitationTemplate::query()->get();
        $this->assertCount(7, $templates);

        foreach ($templates as $template) {
            $this->get('/templates/'.$template->slug.'/preview')
                ->assertOk()
                ->assertSee('Template preview: '.$template->name)
                ->assertSee('noindex, nofollow', false);
        }
    }

    public function test_draft_template_preview_is_not_found(): void
    {
        $template = InvitationTemplate::factory()->draft()->create();

        $this->get('/templates/'.$template->slug.'/preview')->assertNotFound();
    }

    public function test_landing_page_shows_published_templates_only(): void
    {
        $published = InvitationTemplate::factory()->create(['name' => 'Published Design']);
        InvitationTemplate::factory()->draft()->create(['name' => 'Hidden Draft Design']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Published Design')
            ->assertDontSee('Hidden Draft Design')
            ->assertSee(route('templates.preview', $published));
    }
}
