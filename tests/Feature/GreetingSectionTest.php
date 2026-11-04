<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use App\Services\Invitation\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section "greeting": an ornament, an opening line, the invitation message and the names.
 */
class GreetingSectionTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNS = ['classic', 'botanical-cream', 'mocha-glass'];

    private const GREETING = [
        'emblem' => '/illustrations/ornament-flower-corner.svg',
        'opening' => 'Bismillahirrahmanirrahim',
        'message' => 'Dengan penuh rasa syukur kami mengundang Bapak/Ibu/Saudara/i.',
        'names' => 'Alya Salsabila & Pradana Wicaksana',
    ];

    public function test_every_design_renders_the_greeting(): void
    {
        foreach (self::DESIGNS as $design) {
            $html = $this->render($design, self::GREETING);

            $this->assertStringContainsString('data-section="greeting"', $html, "[{$design}]");
            $this->assertStringContainsString('ornament-flower-corner.svg', $html, "[{$design}]");
            $this->assertStringContainsString('Bismillahirrahmanirrahim', $html, "[{$design}]");
            $this->assertStringContainsString('Dengan penuh rasa syukur kami mengundang', $html, "[{$design}]");
            $this->assertMatchesRegularExpression('/Alya Salsabila.*&amp;.*Pradana Wicaksana/s', $html, "[{$design}]");
        }
    }

    public function test_empty_names_use_the_cover_title_and_empty_lines_are_left_out(): void
    {
        foreach (self::DESIGNS as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => ['cover', 'greeting']],
                'sample_content' => ['cover' => ['title' => 'Arina & Bagas'], 'greeting' => ['opening' => '', 'message' => '', 'names' => '']],
            ]);

            $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();
            $greeting = substr($html, strpos($html, 'data-section="greeting"'));

            $this->assertMatchesRegularExpression('/Arina.*&amp;.*Bagas/s', $greeting, "[{$design}]");
            $this->assertStringNotContainsString('greeting__opening', $greeting, "[{$design}]");
            $this->assertStringNotContainsString('greeting__emblem', $greeting, "[{$design}]");
        }
    }

    public function test_the_ornament_url_is_checked_and_the_default_message_is_translated(): void
    {
        $html = $this->render('botanical-cream', [...self::GREETING, 'emblem' => 'javascript:alert(1)']);
        $this->assertStringNotContainsString('javascript:alert', $html);

        app()->setLocale('id');
        $this->assertSame('Dengan penuh rasa syukur, kami mengundang Bapak/Ibu/Saudara/i untuk turut merayakan hari bahagia ini bersama kami.', app(SectionRegistry::class)->defaults('greeting')['message']);
        $this->assertContains('emblem', app(SectionRegistry::class)->adminOnlyKeys('greeting'));
    }

    public function test_new_invitations_copy_the_greeting(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover', 'greeting', 'closing']],
            'default_configuration' => ['greeting' => ['opening' => 'Bismillahirrahmanirrahim']],
        ]);

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->assertSame('Bismillahirrahmanirrahim', $invitation->sections()->where('type', 'greeting')->first()->configuration['opening']);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertSee('Bismillahirrahmanirrahim');
    }

    /**
     * @param  array<string, mixed>  $greeting
     */
    private function render(string $design, array $greeting): string
    {
        $template = InvitationTemplate::factory()->create([
            'design' => $design,
            'schema' => ['sections' => ['cover', 'greeting', 'closing']],
            'sample_content' => ['greeting' => $greeting],
        ]);

        return $this->get(route('templates.preview', $template))->assertOk()->getContent();
    }
}
