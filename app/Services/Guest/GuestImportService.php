<?php

namespace App\Services\Guest;

use App\Enums\GuestInvitationStatus;
use App\Enums\RsvpStatus;
use App\Imports\GuestRowsImport;
use App\Jobs\ProcessGuestImport;
use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Two-step guest import (rules.md section 34): analyze → preview → commit.
 * The analysis is cached as plain arrays (never PHP objects) between the two steps.
 */
class GuestImportService
{
    public const MAX_ROWS = 5000;

    public const QUEUE_THRESHOLD = 1000;

    private const CACHE_MINUTES = 30;

    private const INSERT_CHUNK = 500;

    /**
     * Accepted header names per field (snake_cased by the reader).
     */
    private const COLUMN_ALIASES = [
        'name' => ['name', 'nama', 'guest_name', 'nama_tamu', 'full_name'],
        'phone' => ['phone', 'phone_number', 'telepon', 'no_hp', 'nomor_hp', 'no_telp', 'whatsapp', 'wa', 'hp'],
        'email' => ['email', 'e_mail'],
        'group' => ['group', 'grup', 'kelompok', 'kategori', 'category'],
        'number_of_guests' => ['guests', 'number_of_guests', 'guest_count', 'jumlah', 'jumlah_tamu', 'pax'],
    ];

    /**
     * Upload validation rules for the import file.
     *
     * @return array<int, string>
     */
    public static function fileRules(): array
    {
        return ['required', 'file', 'max:2048', 'mimes:csv,txt,xlsx,xls'];
    }

    /**
     * @return array{token: string, total: int, valid_count: int, invalid_count: int, invalid: array<int, array{row: int, name: string, errors: array<int, string>}>, sample: array<int, array<string, mixed>>, remaining: ?int}
     */
    public function analyze(Invitation $invitation, User $user, UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => __('A file can contain at most :max guests.', ['max' => self::MAX_ROWS])]);
        }

        $existingPhones = array_flip($invitation->guests()->whereNotNull('phone')->pluck('phone')->all());
        $existingNames = $invitation->guests()->get(['name', 'phone'])
            ->mapWithKeys(fn ($guest): array => [$this->guestKey($guest->name, $guest->phone) => true])
            ->all();

        $valid = [];
        $invalid = [];
        $seenPhones = [];
        $seenGuests = [];
        $remaining = app(SubscriptionLimitService::class)->remainingGuests($invitation);

        foreach ($rows as $index => $raw) {
            $row = $this->mapColumns($raw);
            $rowNumber = $index + 2;
            $errors = [];

            $phone = GuestService::normalizePhone($row['phone']);
            $guestKey = $this->guestKey($row['name'], $phone);

            if ($row['name'] === '') {
                $errors[] = __('Name is required.');
            } elseif (mb_strlen($row['name']) > 120) {
                $errors[] = __('Name is too long.');
            }

            if ($row['phone'] !== '' && ! GuestService::isValidPhone($phone)) {
                $errors[] = __('Phone number format is invalid.');
            } elseif ($phone !== null && isset($seenPhones[$phone])) {
                $errors[] = __('Duplicate phone in this file (row :row).', ['row' => $seenPhones[$phone]]);
            } elseif ($phone !== null && isset($existingPhones[$phone])) {
                $errors[] = __('A guest with this phone already exists.');
            }

            if ($row['email'] !== '' && filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = __('Email is invalid.');
            }

            if ($row['number_of_guests'] !== '' && (! ctype_digit($row['number_of_guests']) || (int) $row['number_of_guests'] < 1 || (int) $row['number_of_guests'] > 20)) {
                $errors[] = __('Guests must be a number between 1 and 20.');
            }

            if ($row['name'] !== '' && (isset($seenGuests[$guestKey]) || isset($existingNames[$guestKey]))) {
                $errors[] = __('Duplicate guest.');
            }

            if ($errors === [] && $remaining !== null && count($valid) >= $remaining) {
                $errors[] = __('Over your plan guest limit.');
            }

            if ($errors !== []) {
                $invalid[] = ['row' => $rowNumber, 'name' => Str::limit($row['name'], 60), 'errors' => $errors];

                continue;
            }

            if ($phone !== null) {
                $seenPhones[$phone] = $rowNumber;
            }
            $seenGuests[$guestKey] = true;

            $valid[] = [
                'name' => $row['name'],
                'phone' => $phone,
                'email' => $row['email'] ?: null,
                'group' => Str::limit($row['group'], 60, '') ?: null,
                'number_of_guests' => $row['number_of_guests'] === '' ? 1 : (int) $row['number_of_guests'],
            ];
        }

        $token = Str::random(32);
        Cache::put($this->cacheKey($user, $invitation, $token), $valid, now()->addMinutes(self::CACHE_MINUTES));

        return [
            'token' => $token,
            'total' => count($rows),
            'valid_count' => count($valid),
            'invalid_count' => count($invalid),
            'invalid' => array_slice($invalid, 0, 100),
            'sample' => array_slice($valid, 0, 5),
            'remaining' => $remaining,
        ];
    }

    /**
     * Commit a previously analyzed import. Large imports are queued (rules.md section 76).
     *
     * @return array{queued: bool, count: int}
     */
    public function commit(Invitation $invitation, User $user, string $token): array
    {
        $key = $this->cacheKey($user, $invitation, $token);
        $rows = Cache::pull($key);

        if (! is_array($rows)) {
            throw ValidationException::withMessages(['file' => __('This import preview has expired. Please upload the file again.')]);
        }

        $job = new ProcessGuestImport($invitation->ulid, $user->ulid, $rows);
        $queued = count($rows) > self::QUEUE_THRESHOLD;

        $queued ? dispatch($job) : dispatch_sync($job);

        return ['queued' => $queued, 'count' => count($rows)];
    }

    /**
     * Save analyzed rows in one transaction, so a failed attempt leaves nothing behind and a retry
     * starts clean. Rows matching an existing guest (same phone, or same name and phone as in the
     * preview) are skipped, which also makes a retry after a successful commit harmless.
     *
     * @param  array<int, array{name: string, phone: ?string, email: ?string, group: ?string, number_of_guests: int}>  $rows
     * @return array{imported: int, skipped: int}
     */
    public function persist(Invitation $invitation, array $rows): array
    {
        return DB::transaction(function () use ($invitation, $rows): array {
            // Two imports into the same invitation wait for each other instead of sharing one guest quota.
            Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first(['id']);

            $existing = $invitation->guests()->get(['name', 'phone']);
            $phones = array_flip($existing->pluck('phone')->filter()->all());
            $known = $existing->mapWithKeys(fn (Guest $guest): array => [$this->guestKey($guest->name, $guest->phone) => true])->all();
            $remaining = app(SubscriptionLimitService::class)->remainingGuests($invitation);

            $groups = $invitation->guestGroups()->get(['id', 'name'])
                ->mapWithKeys(fn (GuestGroup $group): array => [mb_strtolower($group->name) => $group->getKey()])
                ->all();
            $groupSort = (int) $invitation->guestGroups()->max('sort_order');

            $now = now();
            $pending = [];
            $imported = 0;

            foreach ($rows as $row) {
                $name = trim($row['name']);
                $phone = $row['phone'] ?? null;
                $key = $this->guestKey($name, $phone);

                if (isset($known[$key]) || ($phone !== null && isset($phones[$phone])) || ($remaining !== null && $imported >= $remaining)) {
                    continue;
                }

                $groupName = Str::limit(trim((string) ($row['group'] ?? '')), 60, '');
                $groupId = null;

                if ($groupName !== '') {
                    $groupId = $groups[mb_strtolower($groupName)] ??= $invitation->guestGroups()
                        ->create(['name' => $groupName, 'sort_order' => ++$groupSort])
                        ->getKey();
                }

                $pending[] = [
                    'ulid' => (new Guest)->newUniqueId(),
                    'invitation_id' => $invitation->getKey(),
                    'guest_group_id' => $groupId,
                    'name' => $name,
                    'phone' => $phone,
                    'email' => ($row['email'] ?? null) ?: null,
                    'number_of_guests' => max(1, (int) ($row['number_of_guests'] ?? 1)),
                    'token' => Str::random(Guest::TOKEN_LENGTH),
                    'rsvp_status' => RsvpStatus::Pending->value,
                    'invitation_status' => GuestInvitationStatus::NotSent->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $known[$key] = true;
                if ($phone !== null) {
                    $phones[$phone] = true;
                }
                $imported++;

                if (count($pending) === self::INSERT_CHUNK) {
                    $this->insertGuests($pending);
                    $pending = [];
                }
            }

            $this->insertGuests($pending);

            return ['imported' => $imported, 'skipped' => count($rows) - $imported];
        });
    }

    /**
     * Bulk insert skips model events, so tokens are checked for collisions here per chunk.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertGuests(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        while ($taken = Guest::withTrashed()->whereIn('token', array_column($rows, 'token'))->pluck('token')->flip()->all()) {
            foreach ($rows as $index => $row) {
                if (isset($taken[$row['token']])) {
                    $rows[$index]['token'] = Str::random(Guest::TOKEN_LENGTH);
                }
            }
        }

        Guest::query()->insert($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $readerType = match (strtolower($file->getClientOriginalExtension())) {
            'xlsx' => ExcelFormat::XLSX,
            'xls' => ExcelFormat::XLS,
            default => ExcelFormat::CSV,
        };

        $sheets = Excel::toArray(new GuestRowsImport, $file, null, $readerType);

        return array_values($sheets[0] ?? []);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{name: string, phone: string, email: string, group: string, number_of_guests: string}
     */
    private function mapColumns(array $raw): array
    {
        $mapped = [];

        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            $value = '';

            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $raw) && $raw[$alias] !== null && $raw[$alias] !== '') {
                    $value = $raw[$alias];
                    break;
                }
            }

            $mapped[$field] = trim(is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value);
        }

        return $mapped;
    }

    private function guestKey(string $name, ?string $phone): string
    {
        return mb_strtolower(trim($name)).'|'.($phone ?? '');
    }

    private function cacheKey(User $user, Invitation $invitation, string $token): string
    {
        return "guest-import:{$user->ulid}:{$invitation->ulid}:{$token}";
    }
}
