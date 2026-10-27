<div class="space-y-6">
    <x-ui.card padding="p-0">
        <div class="flex flex-wrap gap-2 border-b border-line p-4">
            <input type="search" wire:model.live.debounce.400ms="search" aria-label="{{ __('Search') }}" placeholder="{{ __('Search action, user or IP') }}"
                class="h-9 min-w-[14rem] flex-1 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
            <select wire:model.live="group" aria-label="{{ __('Filter by type') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                <option value="">{{ __('All activity') }}</option>
                @foreach ($groups as $groupKey => $groupLabel)
                    <option value="{{ $groupKey }}">{{ __($groupLabel) }}</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="from" aria-label="{{ __('From') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
            <input type="date" wire:model.live="to" aria-label="{{ __('To') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
        </div>

        @if ($logs->isEmpty())
            <x-ui.empty-state icon="fa-solid fa-clock-rotate-left" :title="__('No activity found.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($logs as $log)
                    <li wire:key="audit-{{ $log->created_at->getTimestamp() }}-{{ $loop->index }}" x-data="{ open: false }" class="px-5 py-3 text-sm">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-mono text-xs font-medium text-ink">{{ $log->action }}</span>
                            @if ($log->auditable_type)
                                <span class="text-xs text-muted">{{ class_basename($log->auditable_type) }}</span>
                            @endif
                            <span class="min-w-0 flex-1 truncate text-xs text-ink-soft">
                                @if ($log->user && $log->user->deleted_at)
                                    {{ $log->user->email }} ({{ __('deleted') }})
                                @elseif ($log->user)
                                    <a href="{{ route('admin.users.show', $log->user) }}" class="hover:underline">{{ $log->user->email }}</a>
                                @else
                                    {{ __('System') }}
                                @endif
                            </span>
                            <span class="text-xs tabular-nums text-muted" title="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->translatedFormat('d M Y, H:i:s') }}</span>
                            <button type="button" x-on:click="open = ! open" class="rounded px-1.5 text-muted hover:text-ink" x-bind:aria-expanded="open" aria-label="{{ __('Details') }}">
                                <i class="fa-solid fa-chevron-down text-xs transition" x-bind:class="open && 'rotate-180'" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div x-show="open" x-cloak class="mt-2 space-y-2 rounded-lg bg-paper p-3 text-xs">
                            <p class="text-muted">{{ __('IP address') }}: <span class="text-ink">{{ $log->ip_address ?? '—' }}</span></p>
                            <p class="break-all text-muted">{{ __('Device') }}: <span class="text-ink">{{ $log->user_agent ?? '—' }}</span></p>
                            @if ($log->metadata)
                                <pre class="overflow-x-auto whitespace-pre-wrap break-all font-mono text-ink">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="border-t border-line px-4 py-3">{{ $logs->links() }}</div>
    </x-ui.card>
</div>
