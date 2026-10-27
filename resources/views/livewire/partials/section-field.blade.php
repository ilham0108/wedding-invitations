{{-- Generic editor field. $path is relative to $config (e.g. "people.0.name"). Fields with "variants" only show for those layouts ($variant). --}}
@php
    // Fields can depend on the layout ("variants") and on a design feature ("feature", e.g. desktop_panel).
    $shownForLayout = (! isset($field['variants']) || in_array($variant ?? null, $field['variants'], true))
        && (! isset($field['feature']) || in_array($field['feature'], $designFeatures ?? [], true));
    $value = data_get($config, $path);
    $inputId = 'field-'.str_replace('.', '-', $path);
    $inputClass = 'block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage';

    // A focal point field ("{key}_focus") belongs to the image field next to it and needs that photo.
    if ($field['type'] === 'focus') {
        $focusImage = data_get($config, preg_replace('/[^.]+$/', $field['image'], $path));
        $shownForLayout = $shownForLayout && filled($focusImage);
        $focusPoint = array_map('intval', explode(' ', str_replace('%', '', \App\Support\PhotoStyle::focus($value) ?? '50% 50%')));
    }
@endphp

@if ($shownForLayout)
<div class="space-y-1.5" wire:key="field-{{ $path }}">
    @if (! in_array($field['type'], ['toggle', 'repeater'], true))
        <label for="{{ $inputId }}" class="block text-sm font-medium text-ink">{{ __($field['label']) }}</label>
    @endif

    @switch($field['type'])
        @case('textarea')
            <textarea id="{{ $inputId }}" rows="3" wire:model.live.debounce.700ms="config.{{ $path }}" class="{{ $inputClass }}"></textarea>
            @break

        @case('toggle')
            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" wire:model.live="config.{{ $path }}" class="rounded border-line text-sage focus:ring-sage">
                {{ __($field['label']) }}
            </label>
            @break

        @case('select')
            <select id="{{ $inputId }}" wire:model.live="config.{{ $path }}" class="{{ $inputClass }}">
                @foreach ($field['options'] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}">{{ __($optionLabel) }}</option>
                @endforeach
            </select>
            @break

        @case('date')
        @case('time')
            <input id="{{ $inputId }}" type="{{ $field['type'] }}" wire:model.live.debounce.700ms="config.{{ $path }}" class="{{ $inputClass }}">
            @break

        @case('url')
            <input id="{{ $inputId }}" type="url" placeholder="https://" wire:model.live.debounce.700ms="config.{{ $path }}" class="{{ $inputClass }}">
            @break

        @case('number')
            <input id="{{ $inputId }}" type="number" step="any" wire:model.live.debounce.700ms="config.{{ $path }}" class="{{ $inputClass }}">
            @break

        @case('image')
            <x-ui.upload-progress>
            <div class="flex items-center gap-3">
                @if (filled($value))
                    <img src="{{ $value }}" alt="" class="h-16 w-16 rounded-lg border border-line object-cover">
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-lg border border-dashed border-line text-muted"><i class="fa-regular fa-image" aria-hidden="true"></i></span>
                @endif
                <div class="space-y-1 text-xs">
                    <label class="inline-flex cursor-pointer items-center gap-1 font-medium text-sage hover:text-ink">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> {{ filled($value) ? __('Replace') : __('Upload photo') }}
                        <input id="{{ $inputId }}" type="file" accept="image/jpeg,image/png,image/webp" wire:model="uploads.{{ $path }}" class="sr-only">
                    </label>
                    @if (! empty($field['illustrations']))
                        <button type="button" wire:click="openPicker('{{ $path }}')" class="block font-medium text-sage hover:text-ink">
                            <i class="fa-solid fa-palette" aria-hidden="true"></i> {{ __('Choose illustration') }}
                        </button>
                    @endif
                    @if (filled($value))
                        <button type="button" wire:click="$set('config.{{ $path }}', '')" class="block text-red-600 hover:underline">{{ __('Remove') }}</button>
                    @endif
                </div>
            </div>
            </x-ui.upload-progress>
            @error('uploads.'.$path)
                <p class="text-xs font-medium text-red-600">{{ $message }}</p>
            @enderror
            @break

        @case('focus')
            {{-- Click or drag on the photo; the small previews show how common crops keep that point. --}}
            <div class="space-y-2" x-data="{
                    point: @js($focusPoint),
                    dragging: false,
                    place(event) {
                        const box = this.$refs.photo.getBoundingClientRect();
                        const clamp = (value) => Math.round(Math.min(100, Math.max(0, value)));
                        this.point = [clamp((event.clientX - box.left) / box.width * 100), clamp((event.clientY - box.top) / box.height * 100)];
                    },
                    save() { $wire.set('config.{{ $path }}', this.point[0] + '% ' + this.point[1] + '%') },
                    get position() { return this.point[0] + '% ' + this.point[1] + '%' },
                }">
                <div class="relative inline-block max-w-full cursor-crosshair touch-none select-none overflow-hidden rounded-lg border border-line"
                    x-on:pointerdown="dragging = true; $el.setPointerCapture($event.pointerId); place($event)"
                    x-on:pointermove="dragging && place($event)"
                    x-on:pointerup="if (dragging) { dragging = false; save() }"
                    x-on:pointercancel="dragging = false">
                    <img x-ref="photo" id="{{ $inputId }}" src="{{ $focusImage }}" alt="" draggable="false" class="block max-h-56 w-auto max-w-full">
                    <span class="pointer-events-none absolute h-6 w-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-white/30 shadow-[0_0_0_2px_rgba(0,0,0,0.35)]"
                        x-bind:style="'left: ' + point[0] + '%; top: ' + point[1] + '%'"></span>
                </div>
                <div class="flex items-end gap-2" aria-hidden="true">
                    @foreach (['aspect-square w-12', 'aspect-[4/3] w-16', 'aspect-[4/5] w-12', 'aspect-[16/9] w-20'] as $previewClass)
                        <img src="{{ $focusImage }}" alt="" class="{{ $previewClass }} rounded border border-line object-cover" x-bind:style="'object-position: ' + position">
                    @endforeach
                </div>
                <p class="text-xs text-muted">
                    {{ __('Click or drag on the photo to choose the part that stays visible when it is cropped.') }}
                    <button type="button" x-on:click="point = [50, 50]; save()" class="font-medium text-sage hover:text-ink">{{ __('Reset to center') }}</button>
                </p>
            </div>
            @break

        @case('images')
            <ul wire:sort="sortItem('{{ $path }}', $item, $position)" class="grid grid-cols-3 gap-2">
                @foreach ((array) $value as $index => $image)
                    <li wire:key="image-{{ $path }}-{{ $index }}-{{ md5($image) }}" wire:sort:item="{{ $index }}" class="group relative">
                        <img src="{{ $image }}" alt="" class="aspect-square w-full cursor-grab rounded-lg border border-line object-cover">
                        <div class="absolute inset-x-1 bottom-1 hidden justify-between gap-1 group-hover:flex">
                            <button type="button" wire:click="setAsCover('{{ $path }}', {{ $index }})" class="rounded bg-white/90 px-1.5 py-0.5 text-[10px] font-medium text-ink" title="{{ __('Set as cover') }}">{{ __('Cover') }}</button>
                            <button type="button" wire:click="removeItem('{{ $path }}', {{ $index }})" class="rounded bg-white/90 px-1.5 py-0.5 text-[10px] text-red-600" aria-label="{{ __('Remove photo') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </div>
                    </li>
                @endforeach
            </ul>
            <x-ui.upload-progress>
            <div @class(['grid gap-2', 'grid-cols-2' => ! empty($field['illustrations'])])>
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-line px-3 py-4 text-sm text-ink-soft hover:border-ink/30">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i> {{ __('Upload photos') }}
                    <input id="{{ $inputId }}" type="file" multiple accept="image/jpeg,image/png,image/webp" wire:model="galleryUploads" class="sr-only">
                </label>
                @if (! empty($field['illustrations']))
                    <button type="button" wire:click="openPicker('{{ $path }}')" class="flex items-center justify-center gap-2 rounded-lg border border-dashed border-line px-3 py-4 text-sm text-ink-soft hover:border-ink/30">
                        <i class="fa-solid fa-palette" aria-hidden="true"></i> {{ __('Add illustration') }}
                    </button>
                @endif
            </div>
            </x-ui.upload-progress>
            <p class="text-xs text-muted">{{ __('Drag photos to reorder. Images are resized and converted to WebP.') }}</p>
            @error('galleryUploads')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            @error('galleryUploads.*')<p class="text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            @break

        @case('colors')
            <ul wire:sort="sortItem('{{ $path }}', $item, $position)" class="flex flex-wrap gap-2">
                @foreach ((array) $value as $index => $color)
                    <li wire:key="color-{{ $path }}-{{ $index }}" wire:sort:item="{{ $index }}" class="flex items-center gap-1 rounded-lg border border-line p-1">
                        <input type="color" wire:model.live.debounce.500ms="config.{{ $path }}.{{ $index }}" class="h-7 w-8 cursor-pointer rounded border-0 p-0" aria-label="{{ __('Color :n', ['n' => $index + 1]) }}">
                        <button type="button" wire:click="removeItem('{{ $path }}', {{ $index }})" class="px-1 text-xs text-muted hover:text-red-600" aria-label="{{ __('Remove color') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </li>
                @endforeach
                <li>
                    <button type="button" wire:click="addColor('{{ $path }}')" class="h-9 rounded-lg border border-dashed border-line px-3 text-xs text-ink-soft hover:border-ink/30">+ {{ __('Color') }}</button>
                </li>
            </ul>
            @break

        @case('repeater')
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-ink">{{ __($field['label']) }}</p>
                @if (count((array) $value) < ($field['max'] ?? 20))
                    <button type="button" wire:click="addItem('{{ $path }}')" class="text-xs font-medium text-sage hover:text-ink">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('Add') }}
                    </button>
                @endif
            </div>

            <ul wire:sort="sortItem('{{ $path }}', $item, $position)" class="space-y-3">
                @forelse ((array) $value as $index => $item)
                    <li wire:key="item-{{ $path }}-{{ $index }}" wire:sort:item="{{ $index }}" class="rounded-lg border border-line bg-paper/60 p-3">
                        <div class="mb-2 flex items-center justify-between text-xs text-muted">
                            <span wire:sort:handle class="cursor-grab"><i class="fa-solid fa-grip-vertical mr-1" aria-hidden="true"></i>#{{ $index + 1 }}</span>
                            <button type="button" wire:click="removeItem('{{ $path }}', {{ $index }})" data-confirm-button="{{ __('Yes, remove') }}" data-confirm="{{ __('Remove this item?') }}" class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                        </div>
                        <div class="space-y-3">
                            @foreach ($field['fields'] as $subField)
                                @include('livewire.partials.section-field', ['field' => $subField, 'path' => $path.'.'.$index.'.'.$subField['key']])
                            @endforeach
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed border-line px-3 py-4 text-center text-xs text-muted">{{ __('Nothing here yet.') }}</li>
                @endforelse
            </ul>
            @break

        @default
            <input id="{{ $inputId }}" type="text" wire:model.live.debounce.700ms="config.{{ $path }}" class="{{ $inputClass }}">
    @endswitch

    @error('config.'.$path)
        <p class="text-xs font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>
@endif
