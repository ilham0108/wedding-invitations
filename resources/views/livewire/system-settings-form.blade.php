<form wire:submit="save" class="max-w-2xl space-y-6">
    <x-ui.card :title="__('General')">
        <div class="space-y-5">
            @foreach (['site_name' => 'text', 'support_email' => 'email'] as $key => $inputType)
                <div>
                    <label for="setting-{{ $key }}" class="block text-sm font-medium text-ink">{{ __($definitions[$key]['label']) }}</label>
                    <input id="setting-{{ $key }}" type="{{ $inputType }}" wire:model="settings.{{ $key }}"
                        class="mt-1.5 block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    <p class="mt-1 text-xs text-muted">{{ __($definitions[$key]['help']) }}</p>
                    @error("settings.{$key}")<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach

            <div>
                <label for="setting-default_timezone" class="block text-sm font-medium text-ink">{{ __($definitions['default_timezone']['label']) }}</label>
                <select id="setting-default_timezone" wire:model="settings.default_timezone" class="mt-1.5 block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    @foreach ($timezones as $timezone)
                        <option value="{{ $timezone }}">{{ $timezone }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted">{{ __($definitions['default_timezone']['help']) }}</p>
                @error('settings.default_timezone')<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </x-ui.card>

    <x-ui.card :title="__('Registration')">
        <label class="flex items-start justify-between gap-4">
            <span>
                <span class="block text-sm font-medium text-ink">{{ __($definitions['registration_enabled']['label']) }}</span>
                <span class="mt-0.5 block text-xs text-muted">{{ __($definitions['registration_enabled']['help']) }}</span>
            </span>
            <input type="checkbox" wire:model="settings.registration_enabled" class="mt-1 rounded border-line text-sage focus:ring-sage">
        </label>
    </x-ui.card>

    <div class="flex justify-end">
        <x-ui.button type="submit" icon="fa-regular fa-floppy-disk" wire:loading.attr="disabled" wire:target="save">{{ __('Save settings') }}</x-ui.button>
    </div>
</form>
