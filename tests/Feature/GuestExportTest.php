<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class GuestExportTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    public function test_csv_export_respects_filters_and_neutralizes_formulas(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->attending()->create(['name' => 'Alice Attending', 'phone' => '6281234567890']);
        Guest::factory()->for($invitation)->create(['name' => 'Bob Pending']);
        Guest::factory()->for($invitation)->attending()->create(['name' => '=HYPERLINK("http://evil.test")']);

        $response = $this->actingAs($invitation->user)
            ->get('/dashboard/invitations/'.$invitation->ulid.'/guests/export?format=csv&rsvp=attending')
            ->assertOk()
            ->assertDownload();

        $content = file_get_contents($response->getFile()->getPathname());

        $this->assertStringContainsString('Alice Attending', $content);
        $this->assertStringContainsString('6281234567890', $content);
        $this->assertStringNotContainsString('Bob Pending', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString('/invitation/'.$invitation->slug.'/', $content);
    }

    public function test_xlsx_export_downloads(): void
    {
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create();

        $this->actingAs($invitation->user)
            ->get('/dashboard/invitations/'.$invitation->ulid.'/guests/export?format=xlsx')
            ->assertOk()
            ->assertDownload();
    }

    public function test_other_users_cannot_export(): void
    {
        $invitation = $this->createInvitation();

        $this->actingAs(User::factory()->client()->create())
            ->get('/dashboard/invitations/'.$invitation->ulid.'/guests/export')
            ->assertForbidden();
    }
}
