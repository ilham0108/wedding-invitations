<?php

namespace App\Services\Rsvp;

use App\Enums\RsvpAttendance;
use App\Enums\RsvpStatus;
use App\Events\RsvpSubmitted;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Rsvp;
use App\Services\Invitation\SectionRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Public RSVP rules (rules.md section 36): deadline in the event timezone,
 * one response per invited guest, and a people limit per response.
 */
class RsvpService
{
    public function __construct(private SectionRegistry $registry) {}

    /**
     * Configuration of the invitation's enabled RSVP section, or null when RSVP is off.
     *
     * @return array<string, mixed>|null
     */
    public function sectionConfig(Invitation $invitation): ?array
    {
        $section = $invitation->sections()->where('type', 'rsvp')->where('is_enabled', true)->first(['id', 'configuration']);

        return $section ? $this->registry->sanitize('rsvp', $section->configuration ?? []) : null;
    }

    /**
     * RSVP stays open until the end of the deadline day in the invitation timezone.
     *
     * @param  array<string, mixed>  $config
     */
    public function deadline(Invitation $invitation, array $config): ?CarbonImmutable
    {
        if (empty($config['deadline'])) {
            return null;
        }

        try {
            return CarbonImmutable::parse($config['deadline'], $invitation->timezone)->endOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function isOpen(Invitation $invitation, ?array $config): bool
    {
        if ($config === null || $invitation->hasExpired()) {
            return false;
        }

        $deadline = $this->deadline($invitation, $config);

        return $deadline === null || $deadline->isFuture();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    public function mealOptions(array $config): array
    {
        if (empty($config['ask_meal_preference'])) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $config['meal_options']))));
    }

    public function maxGuests(array $config, ?Guest $guest): int
    {
        return $guest?->number_of_guests ?? max(1, (int) ($config['max_guests'] ?? 5));
    }

    /**
     * @param  array{name?: ?string, attendance: string, number_of_guests?: ?int, meal_preference?: ?string, message?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function submit(Invitation $invitation, array $data, ?Guest $guest = null): Rsvp
    {
        $config = $this->sectionConfig($invitation);

        if (! $this->isOpen($invitation, $config)) {
            throw ValidationException::withMessages([
                'attendance' => $config['closed_message'] ?? __('RSVP is closed.'),
            ])->errorBag('rsvp');
        }

        $attendance = RsvpAttendance::from($data['attendance']);
        $maxGuests = $this->maxGuests($config, $guest);
        $people = $attendance === RsvpAttendance::Attending ? max(1, (int) ($data['number_of_guests'] ?? 1)) : 0;

        if ($people > $maxGuests) {
            throw ValidationException::withMessages([
                'number_of_guests' => __('You can reply for at most :max people.', ['max' => $maxGuests]),
            ])->errorBag('rsvp');
        }

        $meal = $data['meal_preference'] ?? null;
        $mealOptions = $this->mealOptions($config);

        if ($meal !== null && $meal !== '' && $mealOptions !== [] && ! in_array($meal, $mealOptions, true)) {
            throw ValidationException::withMessages(['meal_preference' => __('Please choose one of the meal options.')])->errorBag('rsvp');
        }

        $rsvp = DB::transaction(function () use ($invitation, $guest, $data, $attendance, $people, $meal, $mealOptions): Rsvp {
            $rsvp = $guest?->rsvp()->first() ?? new Rsvp;

            $rsvp->fill([
                'name' => $guest?->name ?? trim((string) $data['name']),
                'attendance' => $attendance,
                'number_of_guests' => $people,
                'meal_preference' => $mealOptions !== [] && $attendance === RsvpAttendance::Attending ? ($meal ?: null) : null,
                'message' => trim((string) ($data['message'] ?? '')) ?: null,
            ]);
            $rsvp->invitation()->associate($invitation);
            $rsvp->guest()->associate($guest);
            $rsvp->save();

            $guest?->forceFill(['rsvp_status' => $attendance->toRsvpStatus()])->save();

            return $rsvp;
        });

        RsvpSubmitted::dispatch($rsvp, $invitation);

        return $rsvp;
    }

    /**
     * Headline numbers for the RSVP dashboard (rules.md section 37).
     *
     * @return array{invited: int, attending: int, not_attending: int, pending: int, people: int}
     */
    public function stats(Invitation $invitation): array
    {
        return [
            'invited' => $invitation->guests()->count(),
            'attending' => $invitation->rsvps()->where('attendance', RsvpAttendance::Attending)->count(),
            'not_attending' => $invitation->rsvps()->where('attendance', RsvpAttendance::NotAttending)->count(),
            'pending' => $invitation->guests()->where('rsvp_status', RsvpStatus::Pending)->count(),
            'people' => (int) $invitation->rsvps()->where('attendance', RsvpAttendance::Attending)->sum('number_of_guests'),
        ];
    }

    /**
     * Attending people per guest group; responses without a personal link form their own bucket.
     *
     * @return array<string, int>
     */
    public function peopleByGroup(Invitation $invitation): array
    {
        return $invitation->rsvps()
            ->where('attendance', RsvpAttendance::Attending)
            ->with('guest:id,guest_group_id', 'guest.group:id,name')
            ->get(['id', 'guest_id', 'number_of_guests'])
            ->groupBy(fn (Rsvp $rsvp): string => match (true) {
                $rsvp->guest === null => __('Public link'),
                $rsvp->guest->group === null => __('No group'),
                default => $rsvp->guest->group->name,
            })
            ->map(fn ($rsvps): int => (int) $rsvps->sum('number_of_guests'))
            ->sortDesc()
            ->all();
    }

    /**
     * Responses per day for the last $days days, in the invitation timezone.
     *
     * @return array<string, int> keyed by Y-m-d, oldest first, zero-filled
     */
    public function responsesPerDay(Invitation $invitation, int $days = 30): array
    {
        $start = CarbonImmutable::now($invitation->timezone)->subDays($days - 1)->startOfDay();

        $counts = $invitation->rsvps()
            ->where('created_at', '>=', $start->utc())
            ->pluck('created_at')
            ->countBy(fn ($createdAt): string => $createdAt->timezone($invitation->timezone)->toDateString());

        $series = [];

        for ($day = $start; $day->lte(CarbonImmutable::now($invitation->timezone)); $day = $day->addDay()) {
            $series[$day->toDateString()] = (int) ($counts[$day->toDateString()] ?? 0);
        }

        return $series;
    }
}
