<div class="viz-root space-y-6" @if ($polling) wire:poll.5s @endif>
    <div class="flex flex-wrap items-center gap-3">
        <x-ui.badge :variant="$campaign->status->badgeVariant()">{{ $campaign->status->label() }}</x-ui.badge>
        <x-ui.badge><i class="fa-brands fa-whatsapp mr-1" aria-hidden="true"></i>{{ $campaign->sender->label() }}</x-ui.badge>
        <p class="text-sm text-muted">
            @if ($campaign->status->value === 'scheduled' && $campaign->scheduled_at)
                {{ __('Scheduled for :time', ['time' => $campaign->scheduled_at->timezone($campaign->timezone)->translatedFormat('l, d F Y H:i').' ('.$campaign->timezone.')']) }}
            @elseif ($campaign->completed_at)
                {{ __('Completed :time', ['time' => $campaign->completed_at->diffForHumans()]) }}
            @elseif ($campaign->started_at)
                {{ __('Started :time', ['time' => $campaign->started_at->diffForHumans()]) }}
            @endif
        </p>
        <div class="ml-auto flex flex-wrap gap-2">
            @if ($campaign->status->value === 'scheduled')
                <x-ui.button type="button" size="sm" icon="fa-regular fa-paper-plane" wire:click="sendNow" data-confirm-variant="default" data-confirm-button="{{ __('Send now') }}" data-confirm="{{ __('Send this campaign now instead of at the scheduled time?') }}">{{ __('Send now') }}</x-ui.button>
            @endif
            @if ($campaign->status->value === 'completed' && $stats['failed'] > 0)
                <x-ui.button type="button" variant="secondary" size="sm" icon="fa-solid fa-rotate-right" wire:click="retryFailed">{{ trans_choice('Retry :count failed|Retry :count failed', $stats['failed']) }}</x-ui.button>
            @endif
            @if ($campaign->status->isCancellable())
                <x-ui.button type="button" variant="ghost" size="sm" class="text-red-600" wire:click="cancel" data-confirm-button="{{ __('Yes, cancel') }}" data-confirm="{{ __('Cancel this campaign? Messages already sent cannot be recalled.') }}">{{ __('Cancel campaign') }}</x-ui.button>
            @endif
        </div>
    </div>
    @error('retry')<p class="text-sm font-medium text-red-600">{{ $message }}</p>@enderror

    {{-- KPI row (rules.md section 44) --}}
    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <x-ui.stat-card :label="__('Total recipients')" :value="Number::format($stats['total'])" icon="fa-solid fa-user-group" />
        <x-ui.stat-card :label="__('Sent')" :value="Number::format($stats['sent'])" icon="fa-regular fa-paper-plane" />
        <x-ui.stat-card :label="__('Delivered')" :value="Number::format($stats['delivered'])" icon="fa-solid fa-check-double" />
        <x-ui.stat-card :label="__('Failed')" :value="Number::format($stats['failed'])" icon="fa-regular fa-circle-xmark" />
        <x-ui.stat-card :label="__('Read')" :value="Number::format($stats['opened'])" icon="fa-regular fa-eye" />
        <x-ui.stat-card :label="__('Opened invitation')" :value="Number::format($stats['clicked'])" icon="fa-solid fa-arrow-pointer" />
    </div>

    {{-- Progress: part-to-whole → one stacked bar (sent · failed · pending) --}}
    <x-ui.card :title="__('Progress')">
        @if ($stats['total'] > 0)
            <div class="flex h-4 w-full gap-[2px] overflow-hidden rounded bg-white" role="img"
                aria-label="{{ collect($progress)->map(fn ($item) => $item['label'].': '.$item['value'])->implode(', ') }}">
                @foreach ($progress as $item)
                    @if ($item['value'] > 0)
                        <div class="h-full" style="flex: {{ $item['value'] }} 1 0; background: {{ $item['color'] }};" title="{{ $item['label'] }}: {{ Number::format($item['value']) }}"></div>
                    @endif
                @endforeach
            </div>
            <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                @foreach ($progress as $item)
                    <li class="flex items-center gap-2 text-ink-soft">
                        <span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $item['color'] }};" aria-hidden="true"></span>
                        {{ $item['label'] }} <span class="font-semibold tabular-nums text-ink">{{ Number::format($item['value']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($polling)
            <p class="mt-3 text-xs text-muted"><i class="fa-solid fa-spinner fa-spin mr-1" aria-hidden="true"></i>{{ __('Sending in the background. This page updates automatically.') }}</p>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('Recipients')" padding="p-0">
        <div class="border-b border-line p-4">
            <select wire:model.live="status" aria-label="{{ __('Filter by status') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </div>
        @if ($recipients->isEmpty())
            <x-ui.empty-state icon="fa-solid fa-filter" :title="__('No recipients match this filter.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($recipients as $recipient)
                    <li wire:key="recipient-{{ $recipient->ulid }}" class="flex flex-wrap items-start gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">{{ $recipient->name }}</p>
                            <p class="text-xs text-muted">+{{ $recipient->phone }}@if ($recipient->sent_at) · {{ __('Sent :time', ['time' => $recipient->sent_at->timezone($campaign->timezone)->translatedFormat('d M H:i')]) }}@endif @if ($recipient->read_at) · {{ __('Read') }}@endif</p>
                            @if ($recipient->error && $recipient->status->value === 'failed')
                                <p class="mt-1 text-xs text-red-600">{{ $recipient->error }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <x-ui.badge :variant="$recipient->status->badgeVariant()">{{ $recipient->status->label() }}</x-ui.badge>
                            @if ($recipient->attempts > 1)
                                <p class="mt-1 text-[11px] text-muted">{{ __(':count attempts', ['count' => $recipient->attempts]) }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line px-4 py-3">{{ $recipients->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('Message')">
        <p class="whitespace-pre-line text-sm text-ink-soft">{{ $campaign->message }}</p>
    </x-ui.card>
</div>
