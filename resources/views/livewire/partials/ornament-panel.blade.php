{{-- Rendered inside TemplateBuilder's section panel: the ornaments of the selected section ($selectedSection). --}}
@php
    $inputClass = 'block w-full rounded-md border-line py-1 text-xs focus:border-sage focus:ring-sage';
    $ornaments = $selectedSection[$ornamentList] ?? [];
    $others = collect($sections)->reject(fn (array $section): bool => $section['key'] === $selectedSection['key'] || empty($section[$ornamentList] ?? []));
@endphp

<div class="border-t border-line pt-5">
    <div class="mb-1 flex items-center justify-between">
        <p class="text-sm font-medium text-ink">{{ __('Ornaments') }} <span class="text-xs font-normal text-muted">({{ count($ornaments) }})</span></p>
        <button type="button" wire:click="$toggle('ornamentPicker')" class="text-xs font-medium text-sage hover:text-ink">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('Add ornament') }}
        </button>
    </div>
    @if ($selectedSection['type'] === 'cover' && $hasDesktopPanel)
        <div class="mb-3 flex rounded-lg border border-line p-0.5 text-xs" role="tablist" aria-label="{{ __('Ornaments for') }}">
            @foreach (['ornaments' => __('Cover (phone screen)'), 'desktop_ornaments' => __('Desktop side panel')] as $list => $listLabel)
                <button type="button" role="tab" wire:click="$set('ornamentTarget', '{{ $list }}')"
                    @class(['flex-1 rounded-md px-2 py-1.5', 'bg-ink text-white' => $ornamentList === $list, 'text-ink-soft hover:bg-paper' => $ornamentList !== $list])>{{ $listLabel }}</button>
            @endforeach
        </div>
    @endif
    <p class="mb-3 text-xs text-muted">{{ __('Transparent images placed on this section. Later ones are drawn on top. Upload your own in Illustrations → Ornaments.') }}</p>

    @if ($ornamentPicker)
        <div class="mb-3 rounded-lg border border-line bg-paper p-2">
            @if ($ornamentIllustrations->isEmpty())
                <p class="p-2 text-center text-xs text-muted">{{ __('No ornaments in the library yet.') }}</p>
            @else
                <ul class="grid max-h-56 grid-cols-4 gap-2 overflow-y-auto">
                    @foreach ($ornamentIllustrations as $illustration)
                        <li wire:key="ornament-option-{{ $illustration->ulid }}">
                            <button type="button" wire:click="addOrnament('{{ $illustration->ulid }}')" title="{{ $illustration->name }}"
                                class="block w-full rounded-md border border-line bg-white p-1 hover:border-sage">
                                <img src="{{ $illustration->url() }}" alt="{{ $illustration->name }}" loading="lazy" class="aspect-square w-full object-contain">
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($others->isNotEmpty())
        <select aria-label="{{ __('Copy ornaments from another section') }}" x-on:change="if ($event.target.value) { $wire.copyOrnamentsFrom($event.target.value); $event.target.value = '' }"
            class="{{ $inputClass }} mb-3">
            <option value="">{{ __('Copy ornaments from another section…') }}</option>
            @foreach ($others as $other)
                <option value="{{ $other['key'] }}">{{ __($registry->label($other['type'])) }} ({{ count($other[$ornamentList]) }})</option>
            @endforeach
        </select>
    @endif

    <ul class="space-y-2">
        @forelse ($ornaments as $index => $ornament)
            <li wire:key="ornament-{{ $selectedSection['key'] }}-{{ $ornamentList }}-{{ $index }}" x-data="{ open: false }" class="rounded-lg border border-line">
                <div class="flex items-center gap-2 p-2">
                    <img src="{{ $ornament['image'] }}" alt="" @class(['h-10 w-10 shrink-0 rounded bg-paper object-contain', '-scale-x-100' => $ornament['flip']])>
                    <button type="button" x-on:click="open = ! open" class="min-w-0 flex-1 text-left text-xs">
                        <span class="block font-medium text-ink">#{{ $index + 1 }} · {{ __($ornamentAnchors[$ornament['anchor']]) }} · {{ $ornament['width'] }}%</span>
                        <span class="block text-muted">{{ __($ornamentEnters[$ornament['enter']]) }} · {{ __($ornamentMotions[$ornament['motion']]) }} · {{ $ornament['layer'] === 'back' ? __('Behind content') : __('In front') }}</span>
                    </button>
                    <div class="flex shrink-0 items-center text-xs text-muted">
                        <button type="button" wire:click="moveOrnament({{ $index }}, -1)" @disabled($loop->first) class="rounded p-1 hover:text-ink disabled:opacity-30" aria-label="{{ __('Send backward') }}" title="{{ __('Send backward') }}"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                        <button type="button" wire:click="moveOrnament({{ $index }}, 1)" @disabled($loop->last) class="rounded p-1 hover:text-ink disabled:opacity-30" aria-label="{{ __('Bring forward') }}" title="{{ __('Bring forward') }}"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>
                        <button type="button" wire:click="duplicateOrnament({{ $index }})" class="rounded p-1 hover:text-ink" aria-label="{{ __('Duplicate') }}"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                        <button type="button" wire:click="removeOrnament({{ $index }})" class="rounded p-1 text-red-600 hover:bg-red-50" aria-label="{{ __('Remove') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                    </div>
                </div>

                <div x-show="open" x-cloak class="grid grid-cols-3 gap-2 border-t border-line p-2 text-[11px] text-muted">
                    @foreach (['x' => [__('Horizontal (%)'), -50, 150, 1], 'y' => [__('Vertical (%)'), -50, 150, 1], 'width' => [__('Width (%)'), 2, 150, 1]] as $key => [$label, $min, $max, $step])
                        <label>{{ $label }}
                            <input type="number" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $ornament[$key] }}"
                                wire:change="updateOrnament({{ $index }}, '{{ $key }}', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                        </label>
                    @endforeach

                    <label class="col-span-2">{{ __('Anchor point') }}
                        <select wire:change="updateOrnament({{ $index }}, 'anchor', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                            @foreach ($ornamentAnchors as $value => $label)
                                <option value="{{ $value }}" @selected($ornament['anchor'] === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>{{ __('Layer') }}
                        <select wire:change="updateOrnament({{ $index }}, 'layer', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                            <option value="front" @selected($ornament['layer'] === 'front')>{{ __('In front') }}</option>
                            <option value="back" @selected($ornament['layer'] === 'back')>{{ __('Behind content') }}</option>
                        </select>
                    </label>

                    <label class="col-span-2">{{ __('Entrance') }}
                        <select wire:change="updateOrnament({{ $index }}, 'enter', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                            @foreach ($ornamentEnters as $value => $label)
                                <option value="{{ $value }}" @selected($ornament['enter'] === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>{{ __('Motion') }}
                        <select wire:change="updateOrnament({{ $index }}, 'motion', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                            @foreach ($ornamentMotions as $value => $label)
                                <option value="{{ $value }}" @selected($ornament['motion'] === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>

                    @foreach (['duration' => __('Duration (s)'), 'delay' => __('Delay (s)')] as $key => $label)
                        <label>{{ $label }}
                            <input type="number" min="{{ $key === 'duration' ? 0.2 : 0 }}" max="8" step="0.1" value="{{ $ornament[$key] }}"
                                wire:change="updateOrnament({{ $index }}, '{{ $key }}', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                        </label>
                    @endforeach
                    <label class="flex items-end gap-1.5 pb-1 text-ink-soft">
                        <input type="checkbox" @checked($ornament['flip']) wire:change="updateOrnament({{ $index }}, 'flip', $event.target.checked ? '1' : '0')" class="rounded border-line text-sage focus:ring-sage">
                        {{ __('Mirror') }}
                    </label>
                </div>
            </li>
        @empty
            <li class="rounded-lg border border-dashed border-line px-3 py-4 text-center text-xs text-muted">{{ __('No ornaments on this section.') }}</li>
        @endforelse
    </ul>
</div>
