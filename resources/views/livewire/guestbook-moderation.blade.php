<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <select wire:model.live="status" aria-label="{{ __('Filter wishes') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                <option value="">{{ __('All wishes') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ __($statusOption->label()) }}</option>
                @endforeach
            </select>
            @if ($pendingCount)
                <x-ui.badge variant="warning" class="self-center">{{ trans_choice(':count waiting for approval|:count waiting for approval', $pendingCount) }}</x-ui.badge>
            @endif
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" wire:model.live="autoApprove" class="rounded border-line text-sage focus:ring-sage">
            {{ __('Auto-approve new wishes') }}
        </label>
    </div>

    <x-ui.card padding="p-0">
        @if ($messages->isEmpty())
            <x-ui.empty-state icon="fa-regular fa-comment-dots" :title="__('No wishes yet.')" :description="__('Wishes from your guests will appear here.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($messages as $message)
                    <li wire:key="message-{{ $message->ulid }}" class="flex flex-wrap items-start gap-3 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-ink">{{ $message->message }}</p>
                            <p class="mt-1 text-xs text-muted">— {{ $message->name }} · {{ $message->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <x-ui.badge :variant="$message->status->badgeVariant()">{{ __($message->status->label()) }}</x-ui.badge>
                            @if ($message->status->value !== 'approved')
                                <x-ui.button type="button" variant="secondary" size="sm" wire:click="approve('{{ $message->ulid }}')">{{ __('Approve') }}</x-ui.button>
                            @endif
                            @if ($message->status->value !== 'hidden')
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="hide('{{ $message->ulid }}')">{{ __('Hide') }}</x-ui.button>
                            @endif
                            <button type="button" wire:click="delete('{{ $message->ulid }}')" data-confirm="{{ __('Delete this message?') }}"
                                class="rounded p-1.5 text-muted hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Delete') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line px-4 py-3">{{ $messages->links() }}</div>
        @endif
    </x-ui.card>
</div>
