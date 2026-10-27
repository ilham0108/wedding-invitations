<?php

namespace App\Livewire;

use App\Enums\RsvpAttendance;
use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Services\Rsvp\RsvpService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * RSVP dashboard (rules.md section 37): headline cards, three charts, and the response list.
 */
class RsvpDashboard extends Component
{
    use ResolvesEditableInvitation, WithPagination;

    #[Url(except: '')]
    public string $attendance = '';

    #[Url(except: '')]
    public string $search = '';

    public function mount(string $invitationUlid): void
    {
        $this->invitationUlid = $invitationUlid;
        $this->invitation();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['attendance', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render(RsvpService $rsvps): View
    {
        $invitation = $this->invitation();
        $stats = $rsvps->stats($invitation);
        $attendanceFilter = RsvpAttendance::tryFrom($this->attendance);
        $search = trim($this->search);

        $responses = $invitation->rsvps()
            ->with('guest:id,ulid,name')
            ->when($attendanceFilter, fn ($query) => $query->where('attendance', $attendanceFilter))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('updated_at')
            ->paginate(20);

        $byGroup = $rsvps->peopleByGroup($invitation);
        $perDay = $rsvps->responsesPerDay($invitation);

        return view('livewire.rsvp-dashboard', [
            'stats' => $stats,
            'responses' => $responses,
            'attendanceBreakdown' => [
                ['label' => __('Attending'), 'value' => $stats['attending'], 'color' => 'var(--viz-series-1)'],
                ['label' => __('Not attending'), 'value' => $stats['not_attending'], 'color' => 'var(--viz-series-2)'],
                ['label' => __('Pending'), 'value' => $stats['pending'], 'color' => 'var(--viz-neutral)'],
            ],
            'attendanceTotal' => $stats['attending'] + $stats['not_attending'] + $stats['pending'],
            'byGroup' => $byGroup,
            'byGroupMax' => max([1, ...array_values($byGroup)]),
            'perDay' => $perDay,
            'perDayMax' => max([1, ...array_values($perDay)]),
            'attendanceOptions' => RsvpAttendance::cases(),
            'timezone' => $invitation->timezone,
        ]);
    }
}
