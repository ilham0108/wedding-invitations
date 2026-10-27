<?php

namespace Tests\Feature;

use App\Enums\RsvpStatus;
use App\Models\Guest;
use App\Services\Guest\GuestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class GuestServiceTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    public function test_phone_numbers_are_normalized_to_country_code_digits(): void
    {
        $this->assertSame('6281234567890', GuestService::normalizePhone('0812-3456-7890'));
        $this->assertSame('6281234567890', GuestService::normalizePhone('+62 812 3456 7890'));
        $this->assertSame('6581234567', GuestService::normalizePhone('+65 8123 4567'));
        $this->assertNull(GuestService::normalizePhone('  '));

        $this->assertTrue(GuestService::isValidPhone('6281234567890'));
        $this->assertFalse(GuestService::isValidPhone('123'));
        $this->assertFalse(GuestService::isValidPhone(GuestService::normalizePhone('abc')));
    }

    public function test_guest_gets_a_random_token_and_group_is_reused_case_insensitively(): void
    {
        $invitation = $this->createInvitation();
        $service = app(GuestService::class);

        $first = $service->create($invitation, ['name' => 'Robert', 'group' => 'Family']);
        $second = $service->create($invitation, ['name' => 'Lisa', 'group' => 'family']);

        $this->assertSame(Guest::TOKEN_LENGTH, strlen($first->token));
        $this->assertNotSame($first->token, $second->token);
        $this->assertSame($first->guest_group_id, $second->guest_group_id);
        $this->assertSame(1, $invitation->guestGroups()->count());
        $this->assertSame(
            route('invitations.personal', ['slug' => $invitation->slug, 'token' => $first->token]),
            $service->personalUrl($first, $invitation),
        );
    }

    public function test_filtered_query_applies_search_group_and_rsvp(): void
    {
        $invitation = $this->createInvitation();
        $service = app(GuestService::class);

        $service->create($invitation, ['name' => 'Robert Family', 'phone' => '081111111111', 'group' => 'Family']);
        $service->create($invitation, ['name' => 'Lisa Office', 'phone' => '082222222222', 'group' => 'Office']);
        $service->create($invitation, ['name' => 'No Group Person']);
        $invitation->guests()->where('name', 'Lisa Office')->update(['rsvp_status' => RsvpStatus::Attending]);

        $family = $invitation->guestGroups()->where('name', 'Family')->value('ulid');
        $names = fn (array $filters): array => $service->filteredQuery($invitation, $filters)->orderBy('name')->pluck('name')->all();

        $this->assertSame(['Robert Family'], $names(['search' => 'robert']));
        $this->assertSame(['Lisa Office'], $names(['search' => '2222']));
        $this->assertSame(['Lisa Office', 'No Group Person', 'Robert Family'], $names(['search' => 'o']));
        $this->assertSame(['Robert Family'], $names(['group' => $family]));
        $this->assertSame(['No Group Person'], $names(['group' => 'none']));
        $this->assertSame(['Lisa Office'], $names(['rsvp' => 'attending']));
        $this->assertSame([], $names(['group' => 'unknown-ulid']));
    }
}
