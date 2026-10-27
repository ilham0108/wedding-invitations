<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
    <div class="space-y-6">
        @if ($provider)
            <x-ui.card padding="p-0">
                <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><i class="fa-brands fa-whatsapp text-lg" aria-hidden="true"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-ink">{{ $provider->name }} <x-ui.badge variant="success" class="ml-1">{{ __('Connected') }}</x-ui.badge></p>
                        <p class="text-xs text-muted">
                            {{ $provider->driver->label() }} · {{ __(':count messages per minute', ['count' => $provider->rate_per_minute]) }}
                            @if ($provider->last_tested_at) · {{ __('Tested :time', ['time' => $provider->last_tested_at->diffForHumans()]) }} @endif
                        </p>
                    </div>
                    <x-ui.button type="button" variant="ghost" size="sm" class="text-red-600" wire:click="disconnect"
                        data-confirm-button="{{ __('Disconnect') }}" data-confirm="{{ $activeOwnCampaigns > 0 ? __(':count running campaigns use this number. Their remaining messages will fail. Disconnect anyway?', ['count' => $activeOwnCampaigns]) : __('Disconnect your WhatsApp number?') }}">{{ __('Disconnect') }}</x-ui.button>
                </div>
                <form wire:submit="sendTest" class="flex flex-wrap items-center gap-2 border-t border-line px-5 py-3">
                    <label for="connection_test_phone" class="text-sm text-ink-soft">{{ __('Send a test message to') }}</label>
                    <input id="connection_test_phone" type="tel" wire:model="testPhone" placeholder="0812 3456 7890" class="h-8 w-44 rounded-lg border-line text-xs focus:border-sage focus:ring-sage">
                    <x-ui.button variant="secondary" size="sm" icon="fa-regular fa-paper-plane" wire:loading.attr="disabled" wire:target="sendTest">{{ __('Send test') }}</x-ui.button>
                </form>
            </x-ui.card>

            @error('testPhone')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @endif

        <x-ui.card :title="$provider ? __('Connection settings') : __('Connect your WhatsApp number')"
            :description="__('Invitations are then sent from your own number, so guests recognize the sender and replies come to you.')">
            <form wire:submit="save" class="space-y-4">
                <x-ui.field name="driver" for="connection_driver" :label="__('Provider')">
                    <select id="connection_driver" wire:model.live="driver" @disabled($connected) class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage disabled:bg-paper">
                        @foreach ($drivers as $driverOption)
                            <option value="{{ $driverOption->value }}">{{ $driverOption->label() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field name="name" for="connection_name" :label="__('Connection name')" class="sm:col-span-2">
                        <input id="connection_name" type="text" wire:model="name" maxlength="80" placeholder="{{ __('e.g. My WhatsApp 0812…') }}" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                    <x-ui.field name="ratePerMinute" for="connection_rate" :label="__('Messages per minute')">
                        <input id="connection_rate" type="number" min="1" max="{{ \App\Livewire\WhatsAppConnection::MAX_RATE_PER_MINUTE }}" wire:model="ratePerMinute" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>

                @foreach ($fields as $field)
                    <x-ui.field :name="'credentials.'.$field['key']" :for="'connection_'.$field['key']" :label="__($field['label'])"
                        :hint="$field['secret'] && ($storedSecrets[$field['key']] ?? false) ? __('Saved. Leave blank to keep the current value.') : null">
                        <input id="connection_{{ $field['key'] }}" type="{{ $field['secret'] ? 'password' : 'text' }}" autocomplete="off"
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

                <div class="flex justify-end">
                    <x-ui.button wire:loading.attr="disabled" wire:target="save">{{ $provider ? __('Save changes') : __('Connect number') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    <div class="space-y-4">
        <x-ui.card :title="__('How to connect (Fonnte)')">
            @if ($tutorial['provider'] === 'file')
                <video src="{{ $tutorial['embed_url'] }}" controls preload="metadata" playsinline class="mb-4 aspect-video w-full rounded-lg bg-ink"
                    aria-label="{{ __('Video tutorial: connect Fonnte') }}"></video>
            @elseif ($tutorial['embed_url'])
                {{-- Click-to-load: the third-party player is only fetched when the client asks for it. --}}
                <div x-data="{ playing: false }" class="relative mb-4 aspect-video overflow-hidden rounded-lg bg-ink">
                    <template x-if="playing">
                        <iframe src="{{ $tutorial['embed_url'] }}" class="absolute inset-0 h-full w-full" title="{{ __('Video tutorial: connect Fonnte') }}"
                            allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                    </template>
                    <button type="button" x-show="! playing" x-on:click="playing = true" class="group absolute inset-0 flex items-center justify-center" aria-label="{{ __('Play video tutorial') }}">
                        @if ($tutorial['thumbnail'])
                            <img src="{{ $tutorial['thumbnail'] }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover opacity-80">
                        @endif
                        <span class="relative flex h-12 w-12 items-center justify-center rounded-full bg-black/60 text-white transition group-hover:scale-105">
                            <i class="fa-solid fa-play" aria-hidden="true"></i>
                        </span>
                    </button>
                </div>
            @endif
            <ol class="list-decimal space-y-2 pl-4 text-sm text-ink-soft">
                <li>{{ __('Create an account at fonnte.com.') }}</li>
                <li>{{ __('Add a device with your WhatsApp number and scan the QR code from WhatsApp on your phone (Linked devices).') }}</li>
                <li>{{ __('Copy the device token and paste it in the API token field.') }}</li>
                <li>{{ __('Save, then send a test message to your own number.') }}</li>
            </ol>
        </x-ui.card>

        <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium"><i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ __('Keep your number safe') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-4 text-xs">
                <li>{{ __('WhatsApp may block numbers that send many messages to people who did not save them.') }}</li>
                <li>{{ __('Send to guests who know you, keep a low speed, and avoid sending the same campaign twice.') }}</li>
                <li>{{ __('Messages from your own number do not use the platform monthly quota; the provider bills you directly.') }}</li>
            </ul>
        </div>
    </div>
</div>
