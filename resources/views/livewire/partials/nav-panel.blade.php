{{--
    Template builder, Theme tab: the floating section menu (decoration "nav_display" and "nav_items", App\Services\Invitation\NavigationMenu).
    Without own entries the menu lists the design's sections automatically; "Edit menu items" copies those so the admin starts from what guests see.
--}}
<div>
    <p class="mb-2 text-sm font-medium text-ink">{{ __('Section menu') }}</p>
    <p class="mb-2 text-xs text-muted">{{ __('Floats at the bottom of the screen. It appears once guests scroll past the cover, so it never covers the open button.') }}</p>
    <div class="flex rounded-lg border border-line p-0.5" role="group" aria-label="{{ __('Section menu') }}">
        @foreach (['show' => __('Show'), 'hide' => __('Hide')] as $key => $displayLabel)
            <button type="button" wire:click="setDecoration('nav_display', '{{ $key }}')" aria-pressed="{{ ($key === 'show') === $navShown ? 'true' : 'false' }}"
                @class(['min-h-9 flex-1 rounded-md px-2 py-1.5 text-xs', 'bg-ink text-white' => ($key === 'show') === $navShown, 'text-ink-soft hover:bg-paper' => ($key === 'show') !== $navShown])>{{ $displayLabel }}</button>
        @endforeach
    </div>

    @if ($navShown)
        @if ($decoration['nav_items'] === [])
            <p class="mt-3 text-xs text-muted">
                {{ __('Picked automatically:') }}
                {{ $navEntries === [] ? __('no matching sections yet.') : collect($navEntries)->map(fn (array $entry): string => $navTypes[$entry['type']] ?? $entry['type'])->implode(', ') }}
            </p>
            <button type="button" wire:click="customizeNav" class="mt-2 flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-line px-3 py-2 text-xs font-medium text-ink hover:border-ink/30">
                <i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('Edit menu items') }}
            </button>
        @else
            <ol class="mt-3 space-y-2">
                @foreach ($navEntries as $index => $item)
                    @php($otherTypes = collect($navEntries)->except($index)->pluck('type')->all())
                    <li wire:key="nav-item-{{ $index }}-{{ $item['type'] }}" class="rounded-lg border border-line p-2.5" x-data="{ icons: false }">
                        <div class="flex items-start gap-2">
                            <button type="button" x-on:click="icons = ! icons" x-bind:aria-expanded="icons" aria-controls="nav-icons-{{ $index }}"
                                aria-label="{{ __('Icon: :name. Choose another', ['name' => __($navIcons[$item['icon']]['label'])]) }}"
                                class="mt-4 flex size-11 shrink-0 items-center justify-center rounded-md border border-line text-ink hover:border-ink/30 active:bg-paper">
                                <i class="{{ $navIcons[$item['icon']]['class'] }}" aria-hidden="true"></i>
                            </button>
                            <div class="min-w-0 flex-1 space-y-2">
                                <div>
                                    <label for="nav-type-{{ $index }}" class="mb-0.5 block text-[11px] font-medium text-ink-soft">{{ __('Section') }}</label>
                                    <select id="nav-type-{{ $index }}" x-on:change="$wire.updateNavItem({{ $index }}, 'type', $event.target.value)"
                                        class="block min-h-9 w-full rounded-md border-line py-1 text-xs">
                                        @foreach ($navTypes as $type => $typeLabel)
                                            <option value="{{ $type }}" @selected($type === $item['type']) @disabled(in_array($type, $otherTypes, true))>{{ $typeLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="nav-label-{{ $index }}" class="mb-0.5 block text-[11px] font-medium text-ink-soft">{{ __('Label') }}</label>
                                    <input id="nav-label-{{ $index }}" type="text" maxlength="{{ config('invitation.navigation.label_max') }}" value="{{ $item['label'] }}"
                                        placeholder="{{ app(\App\Services\Invitation\NavigationMenu::class)->defaultLabel($item['type']) }}"
                                        x-on:change="$wire.updateNavItem({{ $index }}, 'label', $event.target.value)"
                                        class="block min-h-9 w-full rounded-md border-line py-1 text-xs">
                                </div>
                            </div>
                            <div class="mt-4 flex shrink-0 flex-col">
                                <button type="button" wire:click="moveNavItem({{ $index }}, -1)" @disabled($loop->first) aria-label="{{ __('Move up') }}"
                                    class="flex size-9 items-center justify-center rounded-md text-ink-soft hover:bg-paper hover:text-ink disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                                <button type="button" wire:click="moveNavItem({{ $index }}, 1)" @disabled($loop->last) aria-label="{{ __('Move down') }}"
                                    class="flex size-9 items-center justify-center rounded-md text-ink-soft hover:bg-paper hover:text-ink disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
                                <button type="button" wire:click="removeNavItem({{ $index }})" aria-label="{{ __('Remove from menu') }}"
                                    class="flex size-9 items-center justify-center rounded-md text-ink-soft hover:bg-red-50 hover:text-red-700"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div id="nav-icons-{{ $index }}" x-show="icons" x-cloak class="mt-2 grid grid-cols-7 gap-1 border-t border-line pt-2" role="group" aria-label="{{ __('Icons') }}">
                            @foreach ($navIcons as $iconKey => $icon)
                                <button type="button" wire:click="updateNavItem({{ $index }}, 'icon', '{{ $iconKey }}')" x-on:click="icons = false"
                                    aria-label="{{ __($icon['label']) }}" aria-pressed="{{ $iconKey === $item['icon'] ? 'true' : 'false' }}" title="{{ __($icon['label']) }}"
                                    @class(['flex aspect-square min-h-9 items-center justify-center rounded-md border text-sm', 'border-ink bg-paper text-ink' => $iconKey === $item['icon'], 'border-transparent text-ink-soft hover:border-line hover:text-ink' => $iconKey !== $item['icon']])>
                                    <i class="{{ $icon['class'] }}" aria-hidden="true"></i>
                                </button>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ol>

            @if (count($navEntries) < 2)
                <p class="mt-2 text-xs text-muted">{{ __('Guests see the menu once it has at least two items.') }}</p>
            @endif

            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" wire:click="addNavItem" @disabled(count($navEntries) >= $navMax || count($navEntries) >= count($navTypes))
                    class="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg border border-line px-3 py-2 text-xs font-medium text-ink hover:border-ink/30 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-line">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('Add menu item') }}
                </button>
                <button type="button" wire:click="resetNavItems" class="flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-xs text-ink-soft hover:bg-paper hover:text-ink">
                    {{ __('Back to automatic') }}
                </button>
            </div>
            <p class="mt-2 text-xs text-muted">{{ trans_choice('At most :count item. An item whose section is hidden in an invitation is left out.|At most :count items. An item whose section is hidden in an invitation is left out.', $navMax) }}</p>
        @endif
    @endif
</div>
