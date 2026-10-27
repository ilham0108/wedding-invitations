{{-- Rendered inside InvitationEditor; binds to $settings. --}}
<div class="space-y-5">
    <x-ui.field name="settings.title" for="settings_title" :label="__('Invitation title')">
        <input id="settings_title" type="text" wire:model.live.debounce.700ms="settings.title" maxlength="120"
            class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
    </x-ui.field>

    <x-ui.field name="settings.timezone" for="settings_timezone" :label="__('Event timezone')" :hint="__('The countdown and event times use this timezone.')">
        <select id="settings_timezone" wire:model.live="settings.timezone" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
            @foreach ($timezones as $timezone)
                <option value="{{ $timezone }}">{{ $timezone }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <div class="border-t border-line pt-5">
        <p class="text-sm font-medium text-ink">{{ __('Search & sharing') }}</p>
        <p class="mt-0.5 text-xs text-muted">{{ __('Shown when the link is shared on WhatsApp or social media.') }}</p>
    </div>

    <x-ui.field name="settings.meta_title" for="settings_meta_title" :label="__('Share title (optional)')">
        <input id="settings_meta_title" type="text" wire:model.live.debounce.700ms="settings.meta_title" maxlength="120"
            class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
    </x-ui.field>

    <x-ui.field name="settings.meta_description" for="settings_meta_description" :label="__('Share description (optional)')">
        <textarea id="settings_meta_description" rows="3" wire:model.live.debounce.700ms="settings.meta_description" maxlength="300"
            class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage"></textarea>
    </x-ui.field>

    <label class="flex items-start gap-3 text-sm text-ink-soft">
        <input type="checkbox" wire:model.live="settings.allow_search_indexing" class="mt-0.5 rounded border-line text-sage focus:ring-sage">
        <span>
            <span class="font-medium text-ink">{{ __('Allow search engines') }}</span><br>
            <span class="text-xs text-muted">{{ __('Off by default. When on, Google may list this invitation.') }}</span>
        </span>
    </label>
</div>
