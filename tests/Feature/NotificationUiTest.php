<?php

namespace Tests\Feature;

use App\Livewire\GuestTable;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

/**
 * Server side of the SweetAlert2 notifications (resources/js/notifications.js).
 */
class NotificationUiTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    public function test_session_flashes_are_handed_to_the_toast_script_safely(): void
    {
        $user = User::factory()->client()->create();

        $html = $this->actingAs($user)
            ->withSession(['success' => 'Saved <b>"fine"</b>', 'error' => 'Oops'])
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<script type="application/json" id="flash-messages">', $html);
        // Expected escape computed, not typed, so code formatters cannot rewrite it.
        $escapedTag = trim(json_encode('<b>', JSON_HEX_TAG), '"');
        $this->assertStringContainsString($escapedTag, $html, 'Markup in messages is escaped inside the JSON payload.');
        $this->assertStringNotContainsString('Saved <b>', $html);

        preg_match('#<script type="application/json" id="flash-messages">(.*?)</script>#s', $html, $match);
        $this->assertSame([
            ['type' => 'success', 'message' => 'Saved <b>"fine"</b>'],
            ['type' => 'error', 'message' => 'Oops'],
        ], json_decode($match[1], true));
    }

    public function test_no_payload_without_flashes_and_dialog_strings_are_translated(): void
    {
        $html = $this->actingAs(User::factory()->client()->create())->get('/dashboard')->getContent();

        $this->assertStringNotContainsString('flash-messages', $html);
        $this->assertStringContainsString('data-notify-i18n=', $html);
        $this->assertStringContainsString('Yes, delete', $html);
    }

    public function test_destructive_actions_ask_through_data_confirm(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create(['name' => 'Ana']);

        Livewire::actingAs($invitation->user)
            ->test(GuestTable::class, ['invitationUlid' => $invitation->ulid])
            ->assertSeeHtml('data-confirm="Delete Ana?"')
            ->assertDontSeeHtml('wire:confirm');
    }
}
