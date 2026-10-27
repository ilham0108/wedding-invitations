<div class="space-y-6">
    {{-- Summary --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card :label="__('Invited')" :value="Number::format($stats['guests'])" icon="fa-solid fa-user-group" />
        <x-ui.stat-card :label="__('Total people')" :value="Number::format($stats['people'])" icon="fa-solid fa-people-group" />
        <x-ui.stat-card :label="__('Attending')" :value="Number::format($stats['attending'])" icon="fa-regular fa-circle-check" />
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_260px]">
        <x-ui.card padding="p-0">
            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center gap-2 border-b border-line p-4">
                <div class="relative min-w-[12rem] flex-1">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted" aria-hidden="true"></i>
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search name, phone, or email') }}" aria-label="{{ __('Search guests') }}"
                        class="h-9 w-full rounded-lg border-line pl-8 text-sm focus:border-sage focus:ring-sage">
                </div>
                <select wire:model.live="group" aria-label="{{ __('Filter by group') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    <option value="">{{ __('All groups') }}</option>
                    <option value="none">{{ __('No group') }}</option>
                    @foreach ($groups as $groupOption)
                        <option value="{{ $groupOption->ulid }}">{{ $groupOption->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="rsvp" aria-label="{{ __('Filter by RSVP') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    <option value="">{{ __('All RSVP') }}</option>
                    @foreach ($rsvpStatuses as $rsvpStatus)
                        <option value="{{ $rsvpStatus->value }}">{{ __($rsvpStatus->label()) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="status" aria-label="{{ __('Filter by invitation status') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($invitationStatuses as $invitationStatus)
                        <option value="{{ $invitationStatus->value }}">{{ __($invitationStatus->label()) }}</option>
                    @endforeach
                </select>

                <x-ui.dropdown width="w-44">
                    <x-slot:trigger>
                        <x-ui.button type="button" variant="secondary" size="sm" icon="fa-solid fa-file-export">{{ __('Export') }}</x-ui.button>
                    </x-slot:trigger>
                    <a href="{{ route('dashboard.guests.export', ['invitation' => $invitation, 'format' => 'xlsx', ...$exportQuery]) }}" class="block px-4 py-2 text-sm text-ink-soft hover:bg-paper">{{ __('Excel (.xlsx)') }}</a>
                    <a href="{{ route('dashboard.guests.export', ['invitation' => $invitation, 'format' => 'csv', ...$exportQuery]) }}" class="block px-4 py-2 text-sm text-ink-soft hover:bg-paper">{{ __('CSV') }}</a>
                </x-ui.dropdown>

                <x-ui.button type="button" size="sm" icon="fa-solid fa-plus" wire:click="create">{{ __('Add Guest') }}</x-ui.button>
            </div>

            {{-- Bulk actions --}}
            @if ($selected)
                <div class="flex flex-wrap items-center gap-2 border-b border-line bg-sage-soft/50 px-4 py-2 text-sm">
                    <span class="font-medium text-ink">{{ trans_choice(':count guest selected|:count guests selected', count($selected), ['count' => count($selected)]) }}</span>
                    <select wire:model="bulkGroup" aria-label="{{ __('Move to group') }}" class="h-8 rounded-lg border-line text-xs focus:border-sage focus:ring-sage">
                        <option value="">{{ __('Move to group…') }}</option>
                        <option value="none">{{ __('No group') }}</option>
                        @foreach ($groups as $groupOption)
                            <option value="{{ $groupOption->ulid }}">{{ $groupOption->name }}</option>
                        @endforeach
                    </select>
                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="bulkAssignGroup">{{ __('Apply') }}</x-ui.button>
                    <x-ui.button type="button" size="sm" icon="fa-brands fa-whatsapp" wire:click="sendWhatsApp">{{ __('Send via WhatsApp') }}</x-ui.button>
                    <x-ui.button type="button" variant="ghost" size="sm" class="text-red-600" wire:click="bulkDelete" data-confirm="{{ __('Delete the selected guests?') }}">{{ __('Delete') }}</x-ui.button>
                    <button type="button" wire:click="$set('selected', [])" class="ml-auto text-xs text-muted hover:text-ink">{{ __('Clear selection') }}</button>
                </div>
            @endif

            @if ($guests->isEmpty())
                @if ($stats['guests'] === 0)
                    <x-ui.empty-state icon="fa-solid fa-user-plus" :title="__('Start building your guest list.')" :description="__('Add guests one by one or import a spreadsheet.')">
                        <x-slot:action>
                            <x-ui.button type="button" variant="accent" icon="fa-solid fa-plus" wire:click="create">{{ __('Add Guests') }}</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="fa-solid fa-filter" :title="__('No guests match these filters.')" />
                @endif
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-muted">
                            <tr class="border-b border-line">
                                <th class="w-10 px-4 py-3"><span class="sr-only">{{ __('Select') }}</span></th>
                                @foreach (['name' => __('Guest'), 'number_of_guests' => __('Pax'), 'rsvp_status' => __('RSVP')] as $field => $heading)
                                    <th class="px-3 py-3">
                                        <button type="button" wire:click="sortBy('{{ $field }}')" class="inline-flex items-center gap-1 font-semibold uppercase hover:text-ink">
                                            {{ $heading }}
                                            @if ($sortField === $field)
                                                <i class="fa-solid {{ $sortDirection === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[10px]" aria-hidden="true"></i>
                                            @endif
                                        </button>
                                    </th>
                                @endforeach
                                <th class="hidden px-3 py-3 md:table-cell">{{ __('Group') }}</th>
                                <th class="hidden px-3 py-3 md:table-cell">{{ __('Status') }}</th>
                                <th class="px-3 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($guests as $guest)
                                <tr wire:key="guest-{{ $guest->ulid }}" class="align-middle hover:bg-paper/60">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" value="{{ $guest->ulid }}" wire:model.live="selected" aria-label="{{ __('Select :name', ['name' => $guest->name]) }}"
                                            class="rounded border-line text-sage focus:ring-sage">
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-ink">{{ $guest->name }}</p>
                                        <p class="text-xs text-muted">{{ $guest->phone ? '+'.$guest->phone : '' }}{{ $guest->phone && $guest->email ? ' · ' : '' }}{{ $guest->email }}</p>
                                    </td>
                                    <td class="px-3 py-3 tabular-nums">{{ $guest->number_of_guests }}</td>
                                    <td class="px-3 py-3"><x-ui.badge :variant="$guest->rsvp_status->badgeVariant()">{{ __($guest->rsvp_status->label()) }}</x-ui.badge></td>
                                    <td class="hidden px-3 py-3 text-ink-soft md:table-cell">{{ $guest->group?->name ?? '—' }}</td>
                                    <td class="hidden px-3 py-3 md:table-cell"><x-ui.badge :variant="$guest->invitation_status->badgeVariant()">{{ __($guest->invitation_status->label()) }}</x-ui.badge></td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center justify-end gap-1" x-data="{ copied: false }">
                                            <button type="button" class="rounded p-1.5 text-muted hover:bg-sage-soft hover:text-ink" title="{{ __('Copy personal link') }}" aria-label="{{ __('Copy personal link') }}"
                                                x-on:click="navigator.clipboard.writeText(@js($links[$guest->ulid])); copied = true; setTimeout(() => copied = false, 1500)">
                                                <i class="fa-solid" x-bind:class="copied ? 'fa-check text-emerald-600' : 'fa-link'" aria-hidden="true"></i>
                                            </button>
                                            <button type="button" wire:click="edit('{{ $guest->ulid }}')" class="rounded p-1.5 text-muted hover:bg-sage-soft hover:text-ink" aria-label="{{ __('Edit') }}">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                            </button>
                                            <button type="button" wire:click="delete('{{ $guest->ulid }}')" data-confirm="{{ __('Delete :name?', ['name' => $guest->name]) }}" class="rounded p-1.5 text-muted hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Delete') }}">
                                                <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-4 py-3">{{ $guests->links() }}</div>
            @endif
        </x-ui.card>

        {{-- Groups --}}
        <x-ui.card :title="__('Groups')">
            <ul class="space-y-1 text-sm">
                @forelse ($groups as $groupItem)
                    <li wire:key="group-{{ $groupItem->ulid }}" class="group flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-paper">
                        <button type="button" wire:click="$set('group', '{{ $groupItem->ulid }}')" class="truncate text-left text-ink-soft hover:text-ink">{{ $groupItem->name }}</button>
                        <span class="flex items-center gap-2">
                            <span class="text-xs text-muted">{{ $groupItem->guests_count }}</span>
                            <button type="button" wire:click="deleteGroup('{{ $groupItem->ulid }}')" data-confirm="{{ __('Delete this group? Guests stay, without a group.') }}"
                                class="hidden text-xs text-muted hover:text-red-600 group-hover:inline" aria-label="{{ __('Delete group') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </span>
                    </li>
                @empty
                    <li class="text-muted">{{ __('No groups yet, e.g. Family, Friends, Office, VIP.') }}</li>
                @endforelse
            </ul>
            <form wire:submit="addGroup" class="mt-4 flex gap-2">
                <input type="text" wire:model="newGroupName" maxlength="60" placeholder="{{ __('New group') }}" aria-label="{{ __('New group name') }}"
                    class="h-9 min-w-0 flex-1 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                <x-ui.button size="sm">{{ __('Add') }}</x-ui.button>
            </form>
            @error('newGroupName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </x-ui.card>
    </div>

    {{-- Guest form modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.set('showForm', false)">
            <div class="fixed inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative w-full max-w-lg space-y-4 rounded-xl bg-white p-6 shadow-soft">
                <h2 class="text-lg font-semibold text-ink">{{ $editingGuest ? __('Edit guest') : __('Add guest') }}</h2>

                <x-ui.field name="name" for="guest_name" :label="__('Name')">
                    <input id="guest_name" type="text" wire:model="form.name" maxlength="120" required autofocus class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="phone" for="guest_phone" :label="__('WhatsApp number')">
                        <input id="guest_phone" type="tel" wire:model="form.phone" placeholder="0812 3456 7890" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                    <x-ui.field name="email" for="guest_email" :label="__('Email')">
                        <input id="guest_email" type="email" wire:model="form.email" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field name="group" for="guest_group" :label="__('Group')" class="sm:col-span-2">
                        <input id="guest_group" type="text" list="guest-groups" wire:model="form.group" maxlength="60" placeholder="{{ __('e.g. Family') }}" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                        <datalist id="guest-groups">
                            @foreach ($groups as $groupOption)
                                <option value="{{ $groupOption->name }}"></option>
                            @endforeach
                        </datalist>
                    </x-ui.field>
                    <x-ui.field name="number_of_guests" for="guest_pax" :label="__('Guests')">
                        <input id="guest_pax" type="number" min="1" max="20" wire:model="form.number_of_guests" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>

                <x-ui.field name="table_name" for="guest_table" :label="__('Table (optional)')">
                    <input id="guest_table" type="text" wire:model="form.table_name" maxlength="60" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                </x-ui.field>

                <x-ui.field name="notes" for="guest_notes" :label="__('Notes (optional)')">
                    <textarea id="guest_notes" rows="2" wire:model="form.notes" maxlength="1000" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage"></textarea>
                </x-ui.field>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" variant="secondary" wire:click="$set('showForm', false)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button wire:loading.attr="disabled" wire:target="save">{{ __('Save guest') }}</x-ui.button>
                </div>
            </form>
        </div>
    @endif
</div>
