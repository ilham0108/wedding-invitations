<?php

namespace App\Services\Guest;

use App\Enums\GuestInvitationStatus;
use App\Enums\RsvpStatus;
use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\Invitation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Normalize to digits with country code (Indonesia by default): "0812-345" → "62812345".
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    public static function isValidPhone(?string $normalizedPhone): bool
    {
        return $normalizedPhone !== null && preg_match('/^[1-9]\d{7,14}$/', $normalizedPhone) === 1;
    }

    public function phoneExists(Invitation $invitation, string $normalizedPhone, ?Guest $ignore = null): bool
    {
        return $invitation->guests()
            ->where('phone', $normalizedPhone)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();
    }

    /**
     * @param  array{name: string, phone?: ?string, email?: ?string, group?: ?string, number_of_guests?: int, table_name?: ?string, notes?: ?string}  $data
     */
    public function create(Invitation $invitation, array $data): Guest
    {
        return $invitation->guests()->create($this->attributes($invitation, $data));
    }

    /**
     * @param  array{name: string, phone?: ?string, email?: ?string, group?: ?string, number_of_guests?: int, table_name?: ?string, notes?: ?string}  $data
     */
    public function update(Guest $guest, array $data): Guest
    {
        $guest->update($this->attributes($guest->loadMissing('invitation')->invitation, $data));

        return $guest;
    }

    /**
     * @param  array<int, string>  $guestUlids
     */
    public function deleteMany(Invitation $invitation, array $guestUlids, User $user): int
    {
        $guests = $invitation->guests()->whereIn('ulid', $guestUlids)->get(['id', 'ulid', 'name']);

        DB::transaction(fn () => $guests->each->delete());

        if ($guests->isNotEmpty()) {
            $this->auditLogger->record('guest.deleted', $user, $invitation, [
                'count' => $guests->count(),
                'guests' => $guests->pluck('ulid')->all(),
            ]);
        }

        return $guests->count();
    }

    /**
     * @param  array<int, string>  $guestUlids
     */
    public function assignGroup(Invitation $invitation, array $guestUlids, ?string $groupUlid): int
    {
        $groupId = $groupUlid ? $invitation->guestGroups()->where('ulid', $groupUlid)->firstOrFail()->getKey() : null;

        return $invitation->guests()->whereIn('ulid', $guestUlids)->update(['guest_group_id' => $groupId]);
    }

    /**
     * Find a group by name (case-insensitive) or create it.
     */
    public function findOrCreateGroup(Invitation $invitation, string $name): GuestGroup
    {
        $name = Str::limit(trim($name), 60, '');

        return $invitation->guestGroups()->where('name', $name)->first()
            ?? $invitation->guestGroups()->create([
                'name' => $name,
                'sort_order' => (int) $invitation->guestGroups()->max('sort_order') + 1,
            ]);
    }

    public function deleteGroup(GuestGroup $group): void
    {
        $group->delete();
    }

    /**
     * Guest query with the dashboard filters applied; shared by the table and the export.
     *
     * @param  array{search?: ?string, group?: ?string, rsvp?: ?string, status?: ?string}  $filters
     */
    public function filteredQuery(Invitation $invitation, array $filters): HasMany
    {
        $group = $filters['group'] ?? null;
        $rsvp = RsvpStatus::tryFrom((string) ($filters['rsvp'] ?? ''));
        $status = GuestInvitationStatus::tryFrom((string) ($filters['status'] ?? ''));

        return $invitation->guests()
            ->with('group:id,ulid,name')
            ->search($filters['search'] ?? null)
            ->when($group === 'none', fn ($query) => $query->whereNull('guest_group_id'))
            ->when($group && $group !== 'none', fn ($query) => $query->where(
                'guest_group_id',
                $invitation->guestGroups()->where('ulid', $group)->value('id') ?? 0,
            ))
            ->when($rsvp, fn ($query) => $query->where('rsvp_status', $rsvp))
            ->when($status, fn ($query) => $query->where('invitation_status', $status));
    }

    public function personalUrl(Guest $guest, Invitation $invitation): string
    {
        return route('invitations.personal', ['slug' => $invitation->slug, 'token' => $guest->token]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Invitation $invitation, array $data): array
    {
        $groupName = trim((string) ($data['group'] ?? ''));

        return [
            'name' => trim($data['name']),
            'phone' => self::normalizePhone($data['phone'] ?? null),
            'email' => ($data['email'] ?? null) ?: null,
            'guest_group_id' => $groupName !== '' ? $this->findOrCreateGroup($invitation, $groupName)->getKey() : null,
            'number_of_guests' => max(1, (int) ($data['number_of_guests'] ?? 1)),
            'table_name' => ($data['table_name'] ?? null) ?: null,
            'notes' => ($data['notes'] ?? null) ?: null,
        ];
    }
}
