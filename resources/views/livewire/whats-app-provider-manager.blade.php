<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-muted">{{ __('Only the active provider sends messages. Credentials are encrypted and never shown again after saving.') }}</p>
        <x-ui.button type="button" icon="fa-solid fa-plus" wire:click="create">{{ __('Add provider') }}</x-ui.button>
    </div>

    <x-ui.card padding="p-0">
        @if ($providers->isEmpty())
            <x-ui.empty-state icon="fa-brands fa-whatsapp" :title="__('No WhatsApp provider yet.')"
                :description="__('Clients cannot send campaigns until a provider is configured.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($providers as $provider)
                    <li wire:key="provider-{{ $provider->ulid }}" class="flex flex-wrap items-center gap-3 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">
                                {{ $provider->name }}
                                @if ($provider->is_active)
                                    <x-ui.badge variant="success" class="ml-1">{{ __('Active') }}</x-ui.badge>
                                @endif
                            </p>
                            <p class="text-xs text-muted">
                                {{ $provider->driver->label() }} · {{ __(':count messages per minute', ['count' => $provider->rate_per_minute]) }}
                                @if ($provider->last_tested_at) · {{ __('Tested :time', ['time' => $provider->last_tested_at->diffForHumans()]) }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            @unless ($provider->is_active)
                                <x-ui.button type="button" variant="secondary" size="sm" wire:click="activate('{{ $provider->ulid }}')">{{ __('Activate') }}</x-ui.button>
                            @endunless
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="edit('{{ $provider->ulid }}')">{{ __('Edit') }}</x-ui.button>
                            <button type="button" wire:click="delete('{{ $provider->ulid }}')" data-confirm="{{ __('Delete this provider?') }}"
                                class="rounded p-1.5 text-muted hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Delete') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        </div>

                        <form wire:submit="sendTest('{{ $provider->ulid }}')" class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                            <input type="tel" wire:model="testPhone" placeholder="0812 3456 7890" aria-label="{{ __('Test number') }}"
                                class="h-8 w-40 rounded-lg border-line text-xs focus:border-sage focus:ring-sage">
                            <x-ui.button variant="secondary" size="sm" icon="fa-regular fa-paper-plane" wire:loading.attr="disabled" wire:target="sendTest">{{ __('Send test') }}</x-ui.button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    @error('testPhone')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.set('showForm', false)">
            <div class="fixed inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative max-h-[90vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-xl bg-white p-6 shadow-soft">
                <h2 class="text-lg font-semibold text-ink">{{ $editingUlid ? __('Edit provider') : __('Add provider') }}</h2>

                <x-ui.field name="driver" for="provider_driver" :label="__('Provider')">
                    <select id="provider_driver" wire:model.live="driver" @disabled($editingUlid) class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage disabled:bg-paper">
                        @foreach ($drivers as $driverOption)
                            <option value="{{ $driverOption->value }}">{{ $driverOption->label() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field name="name" for="provider_name" :label="__('Name')" class="sm:col-span-2">
                        <input id="provider_name" type="text" wire:model="name" maxlength="80" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                    <x-ui.field name="ratePerMinute" for="provider_rate" :label="__('Messages per minute')">
                        <input id="provider_rate" type="number" min="1" max="600" wire:model="ratePerMinute" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>

                @if ($driver === 'log')
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ __('The log driver writes messages to the application log and never sends them. Use it for local development only.') }}</p>
                @endif

                @foreach ($fields as $field)
                    <x-ui.field :name="'credentials.'.$field['key']" :for="'credential_'.$field['key']" :label="__($field['label'])"
                        :hint="$field['secret'] && ($storedSecrets[$field['key']] ?? false) ? __('Saved. Leave blank to keep the current value.') : null">
                        <input id="credential_{{ $field['key'] }}" type="{{ $field['secret'] ? 'password' : 'text' }}" autocomplete="off"
                            wire:model="credentials.{{ $field['key'] }}" placeholder="{{ $field['placeholder'] ?? '' }}"
                            class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                @endforeach

                @if ($webhookUrl)
                    <div class="rounded-lg bg-paper px-3 py-2 text-xs text-ink-soft">
                        <p class="font-medium text-ink">{{ __('Delivery status webhook') }}</p>
                        <p class="mt-1 break-all font-mono">{{ $webhookUrl }}</p>
                        <p class="mt-1 text-muted">{{ __('Register this URL in the provider dashboard to receive delivered and read statuses.') }}</p>
                    </div>
                @endif

                @if (in_array($driver, ['meta', 'twilio'], true))
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ __('Meta and Twilio only deliver free-form messages to people who messaged you in the last 24 hours. Invitations to new contacts need a template approved by Meta.') }}</p>
                @endif

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" variant="secondary" wire:click="$set('showForm', false)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button wire:loading.attr="disabled" wire:target="save">{{ __('Save provider') }}</x-ui.button>
                </div>
            </form>
        </div>
    @endif
</div>
