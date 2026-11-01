<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_hero(): void
    {
        $this->get('/')
            ->assertOk()
            // Default hero copy from config/site-content.php (the admin CMS can change it).
            ->assertSee('Undangan Digital untuk Momen Spesial Anda')
            ->assertSee(route('register'));
    }

    public function test_landing_page_renders_plans_and_features(): void
    {
        Plan::factory()->features(['max_guests' => 300, 'remove_branding' => true])->create(['name' => 'Basic', 'is_highlighted' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Basic')
            ->assertSee('Konfirmasi Kehadiran')
            ->assertSee('Fitur Unggulan')
            ->assertSee('Plan comparison');
    }

    public function test_unbuilt_plan_features_are_labelled_coming_soon(): void
    {
        Plan::factory()->features(['analytics' => true])->create(['name' => 'Business']);

        $this->get('/')->assertOk()->assertSee('Analytics')->assertSee('Coming soon');
    }

    public function test_the_live_demo_embeds_a_published_template_and_hides_without_one(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="demo"', false)->assertDontSee('#demo', false);

        $template = InvitationTemplate::factory()->create(['name' => 'Taman Pagi']);
        $draft = InvitationTemplate::factory()->draft()->create(['name' => 'Rahasia']);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="demo"', false)
            ->assertSee(route('templates.preview', ['template' => $template, 'embed' => 1]), false)
            ->assertSee('Begini undangan Anda')
            ->assertDontSee('Rahasia');

        // The embedded page drops the preview bar; drafts stay hidden from guests either way.
        $this->get(route('templates.preview', ['template' => $template, 'embed' => 1]))
            ->assertOk()
            ->assertDontSee('inv-preview-bar', false);
        $this->get(route('templates.preview', $template))->assertSee('inv-preview-bar', false);
        $this->get(route('templates.preview', ['template' => $draft, 'embed' => 1]))->assertNotFound();
    }
}
