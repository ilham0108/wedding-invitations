{{-- Rendered inside InvitationEditor and TemplateBuilder; binds to $theme and calls applyPreset(). --}}
<div class="space-y-6">
    <div>
        <p class="mb-2 text-sm font-medium text-ink">{{ __('Presets') }}</p>
        <div class="grid grid-cols-3 gap-2">
            @foreach ($presets as $preset)
                <button type="button" wire:click="applyPreset('{{ $preset }}')"
                    class="rounded-lg border border-line px-2 py-2 text-xs font-medium capitalize text-ink-soft hover:border-ink/30">{{ __(ucfirst($preset)) }}</button>
            @endforeach
        </div>
    </div>

    <div>
        <p class="mb-2 text-sm font-medium text-ink">{{ __('Colors') }}</p>
        <div class="space-y-2">
            @foreach (['primary_color' => __('Primary'), 'secondary_color' => __('Secondary'), 'background_color' => __('Background'), 'surface_color' => __('Surface'), 'text_color' => __('Text')] as $key => $label)
                <label class="flex items-center justify-between gap-3 text-sm text-ink-soft">
                    {{ $label }}
                    <span class="flex items-center gap-2">
                        {{-- Keyed by value so a picker/preset change re-renders it; commits on blur/Enter only when the hex is valid. --}}
                        <input type="text" wire:key="hex-{{ $key }}-{{ $theme[$key] }}" value="{{ $theme[$key] }}" maxlength="7" spellcheck="false" aria-label="{{ __('Hex code') }}"
                            x-on:keydown.enter.prevent="$el.blur()"
                            x-on:change="
                                let hex = '#' + $el.value.trim().replace(/^#/, '').toLowerCase();
                                if (/^#[0-9a-f]{3}$/.test(hex)) hex = '#' + [...hex.slice(1)].map(c => c + c).join('');
                                if (/^#[0-9a-f]{6}$/.test(hex)) { $el.value = hex; $wire.set('theme.{{ $key }}', hex) } else { $el.value = @js($theme[$key]) }
                            "
                            class="w-20 rounded border-line px-2 py-1 font-mono text-xs text-ink-soft focus:border-sage focus:ring-sage">
                        <input type="color" wire:model.live.debounce.400ms="theme.{{ $key }}" class="h-8 w-10 cursor-pointer rounded border border-line bg-white p-0.5">
                    </span>
                </label>
                @error('theme.'.$key)
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            @endforeach
        </div>
    </div>

    @foreach (['font_heading' => __('Heading font'), 'font_body' => __('Body font')] as $key => $label)
        <x-ui.field :name="'theme_'.$key" :label="$label">
            <select id="theme_{{ $key }}" wire:model.live="theme.{{ $key }}" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                @foreach ($fontsByCategory as $category => $fonts)
                    <optgroup label="{{ __(ucfirst($category)) }}">
                        @foreach ($fonts as $font)
                            <option value="{{ $font['name'] }}">{{ $font['name'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </x-ui.field>
    @endforeach

    @foreach (['button_style' => __('Button style'), 'border_radius' => __('Border radius'), 'section_spacing' => __('Section spacing')] as $key => $label)
        <div>
            <p class="mb-2 text-sm font-medium text-ink">{{ $label }}</p>
            <div class="flex rounded-lg border border-line p-0.5">
                @foreach ($themeOptions[$key] as $option)
                    <button type="button" wire:click="$set('theme.{{ $key }}', '{{ $option }}')"
                        @class(['flex-1 rounded-md px-2 py-1.5 text-xs capitalize', 'bg-ink text-white' => $theme[$key] === $option, 'text-ink-soft hover:bg-paper' => $theme[$key] !== $option])>{{ __(ucfirst($option)) }}</button>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
