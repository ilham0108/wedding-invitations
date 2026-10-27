<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_name_is_shown_when_the_logo_file_is_missing(): void
    {
        config(['branding.logo' => 'images/does-not-exist.svg']);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('does-not-exist.svg')
            ->assertSee(config('app.name'));
    }

    public function test_logo_image_is_shown_in_headers_when_the_file_exists(): void
    {
        config(['branding.logo' => 'images/demo/cover.svg']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('src="'.asset('images/demo/cover.svg').'?v=', false)
            ->assertSee('alt="'.config('app.name').'"', false);
    }

    public function test_the_marketing_site_uses_the_leaf_wordmark(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('Digital Invitation');
    }
}
