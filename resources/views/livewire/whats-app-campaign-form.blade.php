<form wire:submit="submit" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
    <div class="space-y-6">
        <x-ui.card :title="__('Campaign')">
            <div class="space-y-5">
                <x-ui.field name="name" for="campaign_name" :label="__('Campaign name')">
                    <input id="campaign_name" type="text" wire:model="name" maxlength="120" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                </x-ui.field>

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium text-ink">{{ __('Send from') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label @class(['flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2 text-sm', 'border-sage bg-sage-soft/40' => $sender === 'own', 'border-line' => $sender !== 'own'])>
                            <input type="radio" wire:model.live="sender" value="own" class="mt-0.5 text-sage focus:ring-sage">
                            <span>
                                <span class="block font-medium text-ink">{{ __('My WhatsApp number') }}</span>
                                <span class="block text-xs text-muted">{{ $ownConnection ? $ownConnection->name : __('Not connected yet') }}</span>
                            </span>
                        </label>
                        <label @class(['flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2 text-sm', 'border-sage bg-sage-soft/40' => $sender === 'platform', 'border-line' => $sender !== 'platform'])>
                            <input type="radio" wire:model.live="sender" value="platform" class="mt-0.5 text-sage focus:ring-sage">
                            <span>
                                <span class="block font-medium text-ink">{{ __('Platform number') }}</span>
                                <span class="block text-xs text-muted">{{ $configured ? __('Sent by :app, uses your monthly quota', ['app' => config('app.name')]) : __('Not available') }}</span>
                            </span>
                        </label>
                    </div>

                    @if ($sender === 'own' && ! $ownConnection)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            {{ __('Connect your WhatsApp number first.') }}
                            <a href="{{ route('dashboard.whatsapp.connection') }}" class="font-medium underline">{{ __('Connect now') }}</a>
                        </p>
                    @elseif ($sender === 'platform' && ! $configured)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ __('WhatsApp provider is not configured.') }} {{ __('Ask the administrator to set one up before sending.') }}</p>
                    @endif
                    @error('sender')<p class="text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                </fieldset>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Recipients')">
            <fieldset class="space-y-3">
                <legend class="sr-only">{{ __('Recipients') }}</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach (['all' => __('All guests'), 'filter' => __('Filter guests'), 'selected' => __('Selected guests')] as $value => $label)
                        <label @class(['flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm', 'border-sage bg-sage-soft/40 text-ink' => $audience === $value, 'border-line text-ink-soft' => $audience !== $value, 'pointer-events-none opacity-50' => $value === 'selected' && $guestUlids === []])>
                            <input type="radio" wire:model.live="audience" value="{{ $value }}" class="text-sage focus:ring-sage" @disabled($value === 'selected' && $guestUlids === [])>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                @if ($audience === 'filter')
                    <div class="grid gap-3 sm:grid-cols-3">
                        <select wire:model.live="filters.group" aria-label="{{ __('Filter by group') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                            <option value="">{{ __('All groups') }}</option>
                            <option value="none">{{ __('No group') }}</option>
                            @foreach ($groups as $groupOption)
                                <option value="{{ $groupOption->ulid }}">{{ $groupOption->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="filters.rsvp" aria-label="{{ __('Filter by RSVP') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                            <option value="">{{ __('All RSVP') }}</option>
                            @foreach ($rsvpStatuses as $rsvpStatus)
                                <option value="{{ $rsvpStatus->value }}">{{ $rsvpStatus->label() }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="filters.status" aria-label="{{ __('Filter by invitation status') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach ($invitationStatuses as $invitationStatus)
                                <option value="{{ $invitationStatus->value }}">{{ $invitationStatus->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <p class="text-sm text-ink-soft">
                    <span class="font-semibold text-ink">{{ trans_choice(':count guest will receive this message.|:count guests will receive this message.', $summary['reachable']) }}</span>
                    @if ($summary['total'] > $summary['reachable'])
                        <span class="text-muted">{{ __(':count without a WhatsApp number will be skipped.', ['count' => $summary['total'] - $summary['reachable']]) }}</span>
                    @endif
                </p>
                @if ($remaining !== null)
                    <p @class(['text-xs', 'text-red-600' => $summary['reachable'] > $remaining, 'text-muted' => $summary['reachable'] <= $remaining])>
                        {{ __(':remaining messages left this month.', ['remaining' => Number::format($remaining)]) }}
                    </p>
                @endif
                @error('audience')<p class="text-sm font-medium text-red-600">{{ $message }}</p>@enderror
            </fieldset>
        </x-ui.card>

        <x-ui.card :title="__('Message')">
            <x-slot:actions>
                <a href="{{ route('dashboard.whatsapp.templates') }}" class="text-xs font-medium text-sage hover:text-ink">{{ __('Manage templates') }}</a>
            </x-slot:actions>

            @if ($templates->isNotEmpty())
                <x-ui.field name="templateUlid" for="campaign_template" :label="__('Start from a template')" class="mb-4">
                    <select id="campaign_template" wire:model.live="templateUlid" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                        <option value="">{{ __('Write a new message') }}</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->ulid }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            @endif

            <x-ui.field name="message" for="campaign_message" :label="__('Message')">
                <x-whatsapp.message-editor model="message" id="campaign_message" :variables="$variables" />
            </x-ui.field>
        </x-ui.card>

        <x-ui.card :title="__('When to send')">
            <div class="space-y-4">
                <div class="grid gap-2 sm:grid-cols-2">
                    <label @class(['flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm', 'border-sage bg-sage-soft/40' => ! $schedule, 'border-line' => $schedule])>
                        <input type="radio" wire:model.live="schedule" value="0" @checked(! $schedule) class="text-sage focus:ring-sage"> {{ __('Send now') }}
                    </label>
                    <label @class(['flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm', 'border-sage bg-sage-soft/40' => $schedule, 'border-line' => ! $schedule])>
                        <input type="radio" wire:model.live="schedule" value="1" @checked($schedule) class="text-sage focus:ring-sage"> {{ __('Schedule') }}
                    </label>
                </div>

                @if ($schedule)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field name="scheduledAt" for="campaign_scheduled_at" :label="__('Send at')">
                            <input id="campaign_scheduled_at" type="datetime-local" wire:model="scheduledAt" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                        </x-ui.field>
                        <x-ui.field name="timezone" for="campaign_timezone" :label="__('Timezone')">
                            <x-ui.select name="timezone" id="campaign_timezone" wire:model="timezone" :value="$timezone" :options="array_combine($timezones, $timezones)" />
                        </x-ui.field>
                    </div>
                @endif
            </div>
        </x-ui.card>
    </div>

    {{-- Preview --}}
    <div class="space-y-4 lg:sticky lg:top-20 lg:self-start">
        <x-ui.card :title="__('Preview')">
            <div class="rounded-xl bg-[#e7ddd3] p-3">
                <div class="ml-auto max-w-[92%] whitespace-pre-line break-words rounded-lg rounded-tr-none bg-[#d9fdd3] px-3 py-2 text-sm text-ink shadow-sm">{{ $preview }}</div>
            </div>
            <p class="mt-2 text-xs text-muted">{{ __('Shown for the first recipient. Each guest gets their own name and personal link.') }}</p>
        </x-ui.card>

        <x-ui.button class="w-full" size="lg" icon="fa-brands fa-whatsapp" :disabled="! $canSend || $summary['reachable'] === 0" wire:loading.attr="disabled" wire:target="submit">
            <span wire:loading.remove wire:target="submit">{{ $schedule ? __('Schedule campaign') : __('Send to :count guests', ['count' => Number::format($summary['reachable'])]) }}</span>
            <span wire:loading wire:target="submit">{{ __('Creating…') }}</span>
        </x-ui.button>
    </div>
</form>
