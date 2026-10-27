<?php

namespace App\Livewire;

use App\Enums\GuestInvitationStatus;
use App\Enums\RsvpStatus;
use App\Http\Requests\StoreGuestRequest;
use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Models\Guest;
use App\Services\Guest\GuestService;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Guest list with search, filters, sorting, bulk actions, groups, and the guest form
 * (rules.md sections 32–33). Only ULIDs ever reach the browser.
 */
class GuestTable extends Component
{
    use ResolvesEditableInvitation, WithPagination;

    private const SORTABLE = ['name', 'created_at', 'number_of_guests', 'rsvp_status'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: '')]
    public string $rsvp = '';

    #[Url(except: '')]
    public string $status = '';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    /** @var array<int, string> */
    public array $selected = [];

    public string $bulkGroup = '';

    public bool $showForm = false;

    public ?string $editingGuest = null;

    /** @var array{name: string, phone: string, email: string, group: string, number_of_guests: int, table_name: string, notes: string} */
    public array $form = [];

    public string $newGroupName = '';

    public function mount(string $invitationUlid): void
    {
        $this->invitationUlid = $invitationUlid;
        $this->invitation();
        $this->resetForm();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'group', 'rsvp', 'status'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $guestUlid): void
    {
        $guest = $this->guest($guestUlid);
        $guest->loadMissing('group:id,name');

        $this->resetValidation();
        $this->editingGuest = $guest->ulid;
        $this->form = [
            'name' => $guest->name,
            'phone' => (string) $guest->phone,
            'email' => (string) $guest->email,
            'group' => (string) $guest->group?->name,
            'number_of_guests' => $guest->number_of_guests,
            'table_name' => (string) $guest->table_name,
            'notes' => (string) $guest->notes,
        ];
        $this->showForm = true;
    }

    public function save(GuestService $guests): void
    {
        $validated = validator($this->form, (new StoreGuestRequest)->rules())->validate();
        $editing = $this->editingGuest ? $this->guest($this->editingGuest) : null;
        $phone = GuestService::normalizePhone($validated['phone'] ?? null);

        if ($phone !== null && $guests->phoneExists($this->invitation(), $phone, $editing)) {
            throw ValidationException::withMessages(['phone' => __('A guest with this phone already exists.')]);
        }

        if ($editing === null && ! app(SubscriptionLimitService::class)->canAddGuest($this->invitation())) {
            throw ValidationException::withMessages(['name' => __('You have reached your guest limit.')]);
        }

        $editing ? $guests->update($editing, $validated) : $guests->create($this->invitation(), $validated);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('guests-changed');
    }

    public function delete(string $guestUlid, GuestService $guests): void
    {
        $guests->deleteMany($this->invitation(), [$this->guest($guestUlid)->ulid], Auth::user());
        $this->selected = array_values(array_diff($this->selected, [$guestUlid]));
    }

    public function bulkDelete(GuestService $guests): void
    {
        $guests->deleteMany($this->invitation(), $this->selected, Auth::user());
        $this->selected = [];
    }

    public function bulkAssignGroup(GuestService $guests): void
    {
        $guests->assignGroup($this->invitation(), $this->selected, $this->bulkGroup === 'none' ? null : ($this->bulkGroup ?: null));
        $this->selected = [];
        $this->bulkGroup = '';
    }

    /**
     * Hand the selection to the WhatsApp campaign form (rules.md section 42).
     */
    public function sendWhatsApp(): void
    {
        $invitation = $this->invitation();
        $ulids = $invitation->guests()->whereIn('ulid', $this->selected)->pluck('ulid')->all();

        if ($ulids === []) {
            return;
        }

        session()->put('whatsapp.selected_guests.'.$invitation->ulid, $ulids);

        $this->redirectRoute('dashboard.whatsapp.create', ['invitation' => $invitation->ulid]);
    }

    public function addGroup(GuestService $guests): void
    {
        $this->validate(['newGroupName' => ['required', 'string', 'max:60']]);

        $guests->findOrCreateGroup($this->invitation(), $this->newGroupName);
        $this->newGroupName = '';
    }

    public function deleteGroup(string $groupUlid, GuestService $guests): void
    {
        $guests->deleteGroup($this->invitation()->guestGroups()->where('ulid', $groupUlid)->firstOrFail());

        if ($this->group === $groupUlid) {
            $this->group = '';
        }
    }

    #[On('guests-imported')]
    public function refreshAfterImport(): void
    {
        $this->resetPage();
    }

    public function render(GuestService $guests): View
    {
        $invitation = $this->invitation();
        $filters = ['search' => $this->search, 'group' => $this->group, 'rsvp' => $this->rsvp, 'status' => $this->status];

        $page = $guests->filteredQuery($invitation, $filters)
            ->orderBy(in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'name', $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate(25);

        return view('livewire.guest-table', [
            'invitation' => $invitation,
            'guests' => $page,
            'links' => $page->getCollection()->mapWithKeys(fn (Guest $guest): array => [$guest->ulid => $guests->personalUrl($guest, $invitation)]),
            'groups' => $invitation->guestGroups()->withCount('guests')->get(['id', 'ulid', 'name']),
            'stats' => [
                'guests' => $invitation->guests()->count(),
                'people' => (int) $invitation->guests()->sum('number_of_guests'),
                'attending' => $invitation->guests()->where('rsvp_status', RsvpStatus::Attending)->count(),
            ],
            'rsvpStatuses' => RsvpStatus::cases(),
            'invitationStatuses' => GuestInvitationStatus::cases(),
            'exportQuery' => array_filter($filters),
        ]);
    }

    private function guest(string $guestUlid): Guest
    {
        return $this->invitation()->guests()->where('ulid', $guestUlid)->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingGuest = null;
        $this->form = ['name' => '', 'phone' => '', 'email' => '', 'group' => '', 'number_of_guests' => 1, 'table_name' => '', 'notes' => ''];
    }
}
