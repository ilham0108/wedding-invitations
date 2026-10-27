<?php

namespace Tests\Feature;

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
            ->assertSee('Fitur Unggulan');
    }
}
