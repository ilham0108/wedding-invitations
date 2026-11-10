{{-- Rendered inside TemplateBuilder's section panel: the ornaments of the selected section ($selectedSection). --}}
@php
    $inputClass = 'block w-full rounded-md border-line py-1 text-xs focus:border-sage focus:ring-sage';
    $ornaments = $selectedSection[$ornamentList] ?? [];
    $others = collect($sections)->reject(fn (array $section): bool => $section['key'] === $selectedSection['key'] || empty($section[$ornamentList] ?? []));
@endphp

{{-- The group around it (template-builder "Ornaments") carries the title and the count. --}}
<div>
    @if ($selectedSection['type'] === 'cover' && $hasDesktopPanel)
        <div class="mb-3 flex rounded-lg border border-line p-0.5 text-xs" role="tablist" aria-label="{{ __('Ornaments for') }}">
            @foreach (['ornaments' => __('Cover (phone screen)'), 'desktop_ornaments' => __('Desktop side panel')] as $list => $listLabel)
                <button type="button" role="tab" wire:click="$set('ornamentTarget', '{{ $list }}')"
                    @class(['flex-1 rounded-md px-2 py-1.5', 'bg-ink text-white' => $ornamentList === $list, 'text-ink-soft hover:bg-paper' => $ornamentList !== $list])>{{ $listLabel }}</button>
            @endforeach
        </div>
    @endif
    <p class="mb-3 text-xs text-muted">{{ __('Transparent images placed on this section. Later ones are drawn on top. Upload your own in Illustrations → Ornaments.') }}</p>
    @if ($ornaments !== [])
        <p class="mb-3 flex gap-2 rounded-lg bg-sage-soft px-3 py-2 text-xs text-ink-soft">
            <i class="fa-solid fa-up-down-left-right mt-0.5 text-sage" aria-hidden="true"></i>
            <span>{{ __('Drag an ornament in the preview to move it; drag its round handle to resize it.') }}</span>
        </p>
    @endif

    <button type="button" wire:click="$toggle('ornamentPicker')" aria-expanded="{{ $ornamentPicker ? 'true' : 'false' }}"
        class="mb-3 flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-ink/25 px-3 py-3 text-sm font-medium text-ink-soft transition-colors hover:border-sage hover:text-ink active:bg-paper focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage">
        <i @class(['fa-solid', 'fa-plus' => ! $ornamentPicker, 'fa-xmark' => $ornamentPicker]) aria-hidden="true"></i> {{ $ornamentPicker ? __('Close the ornament list') : __('Add ornament') }}
    </button>

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
                    <img src="{{ $ornament['image'] }}" alt="" @class(['h-10 w-10 shrink-0 rounded bg-paper object-contain', '-scale-x-100' => $ornament['flip'], '-scale-y-100' => $ornament['flip_y'] ?? false])>
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

                {{-- Six columns: numbers take two (three per row), selects three (two per row) so their labels fit. --}}
                <div x-show="open" x-cloak class="grid grid-cols-6 gap-2 border-t border-line p-2 text-[11px] text-muted">
                    @foreach (['x' => [__('Horizontal (%)'), -50, 150, 1], 'y' => [__('Vertical (%)'), -50, 150, 1], 'width' => [__('Width (%)'), 2, 150, 1]] as $key => [$label, $min, $max, $step])
                        <label class="col-span-2">{{ $label }}
                            <input type="number" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $ornament[$key] }}"
                                x-on:change="$el.value = Math.min({{ $max }}, Math.max({{ $min }}, Number($el.value === '' ? {{ $ornament[$key] }} : $el.value))); $wire.updateOrnament({{ $index }}, '{{ $key }}', $el.value)" class="{{ $inputClass }} mt-0.5">
                        </label>
                    @endforeach

                    <label class="col-span-3">{{ __('Anchor point') }}
                        <select wire:change="updateOrnament({{ $index }}, 'anchor', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                            @foreach ($ornamentAnchors as $value => $label)
                                <option value="{{ $value }}" @selected($ornament['anchor'] === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="col-span-3">{{ __('Layer') }}
                        <select wire:change="updateOrnament({{ $index }}, 'layer', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                            <option value="front" @selected($ornament['layer'] === 'front')>{{ __('Front') }}</option>
                            <option value="back" @selected($ornament['layer'] === 'back')>{{ __('Behind') }}</option>
                        </select>
                    </label>

                    <label class="col-span-3">{{ __('Entrance') }}
                        <select wire:change="updateOrnament({{ $index }}, 'enter', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                            @foreach ($ornamentEnters as $value => $label)
                                <option value="{{ $value }}" @selected($ornament['enter'] === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="col-span-3">{{ __('Motion') }}
                        <select wire:change="updateOrnament({{ $index }}, 'motion', $event.target.value)" class="{{ $inputClass }} mt-0.5">
                            @foreach ($ornamentMotions as $value => $label)
                                <option value="{{ $value }}" @selected($ornament['motion'] === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>

                    @foreach (['duration' => [__('Duration (s)'), 0.2], 'delay' => [__('Delay (s)'), 0]] as $key => [$label, $min])
                        @php($max = 8)
                        <label class="col-span-2">{{ $label }}
                            <input type="number" min="{{ $min }}" max="{{ $max }}" step="0.1" value="{{ $ornament[$key] }}"
                                x-on:change="$el.value = Math.min({{ $max }}, Math.max({{ $min }}, Number($el.value === '' ? {{ $ornament[$key] }} : $el.value))); $wire.updateOrnament({{ $index }}, '{{ $key }}', $el.value)" class="{{ $inputClass }} mt-0.5">
                        </label>
                    @endforeach
                    @foreach (['flip' => __('Flip horizontally'), 'flip_y' => __('Flip vertically')] as $key => $label)
                        <label class="col-span-3 flex items-center gap-1.5 text-ink-soft">
                            <input type="checkbox" @checked($ornament[$key] ?? false) wire:change="updateOrnament({{ $index }}, '{{ $key }}', $event.target.checked ? '1' : '0')" class="rounded border-line text-sage focus:ring-sage">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </li>
        @empty
            <li class="rounded-lg border border-dashed border-line px-3 py-4 text-center text-xs text-muted">{{ __('No ornaments on this section.') }}</li>
        @endforelse
    </ul>
</div>
