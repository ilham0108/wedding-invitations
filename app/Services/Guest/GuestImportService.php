<?php

namespace App\Services\Guest;

use App\Imports\GuestRowsImport;
use App\Jobs\ProcessGuestImport;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
