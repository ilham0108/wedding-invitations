<div class="viz-root space-y-6">
    {{-- KPI row (rules.md section 37) --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-ui.stat-card :label="__('Total invited')" :value="Number::format($stats['invited'])" icon="fa-solid fa-user-group" />
        <x-ui.stat-card :label="__('Attending')" :value="Number::format($stats['attending'])" icon="fa-regular fa-circle-check" />
        <x-ui.stat-card :label="__('Not attending')" :value="Number::format($stats['not_attending'])" icon="fa-regular fa-circle-xmark" />
        <x-ui.stat-card :label="__('Pending')" :value="Number::format($stats['pending'])" icon="fa-regular fa-clock" />
        <x-ui.stat-card :label="__('Total people')" :value="Number::format($stats['people'])" icon="fa-solid fa-people-group" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Attendance: part-to-whole → one stacked horizontal bar --}}
        <x-ui.card :title="__('Attendance')" x-data="{ table: false, tip: null }">
            <x-slot:actions>
                <button type="button" x-on:click="table = ! table" class="text-xs font-medium text-sage hover:text-ink" x-text="table ? @js(__('Show chart')) : @js(__('Show table'))">{{ __('Show table') }}</button>
            </x-slot:actions>

            @if ($attendanceTotal === 0)
                <p class="py-6 text-center text-sm text-muted">{{ __('No guests or responses yet.') }}</p>
            @else
                <div x-show="! table">
                    <div class="relative flex h-6 w-full gap-[2px] overflow-hidden rounded bg-white" role="img"
                        aria-label="{{ collect($attendanceBreakdown)->map(fn ($item) => $item['label'].': '.$item['value'])->implode(', ') }}">
                        @foreach ($attendanceBreakdown as $item)
                            @if ($item['value'] > 0)
                                <div class="h-full transition-opacity" style="flex: {{ $item['value'] }} 1 0; background: {{ $item['color'] }};"
                                    x-on:mouseenter="tip = @js($item['label'].' · '.Number::format($item['value']).' ('.round($item['value'] / $attendanceTotal * 100).'%)')" x-on:mouseleave="tip = null"
                                    x-bind:class="tip && tip.startsWith(@js($item['label'])) ? '' : (tip ? 'opacity-60' : '')"></div>
                            @endif
                        @endforeach
                    </div>
                    <p class="mt-2 h-4 text-xs text-ink-soft" x-text="tip" aria-live="polite"></p>
                    <ul class="mt-2 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        @foreach ($attendanceBreakdown as $item)
                            <li class="flex items-center gap-2 text-ink-soft">
                                <span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $item['color'] }};" aria-hidden="true"></span>
                                {{ $item['label'] }} <span class="font-semibold tabular-nums text-ink">{{ Number::format($item['value']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <table x-cloak x-show="table" class="w-full text-sm">
                    <tbody class="divide-y divide-line">
                        @foreach ($attendanceBreakdown as $item)
                            <tr><td class="py-1.5 text-ink-soft">{{ $item['label'] }}</td><td class="py-1.5 text-right tabular-nums text-ink">{{ Number::format($item['value']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>

        {{-- Guest count: magnitude per group → single-hue horizontal bars --}}
        <x-ui.card :title="__('People attending by group')" x-data="{ table: false }">
            <x-slot:actions>
                <button type="button" x-on:click="table = ! table" class="text-xs font-medium text-sage hover:text-ink" x-text="table ? @js(__('Show chart')) : @js(__('Show table'))">{{ __('Show table') }}</button>
            </x-slot:actions>

            @if ($byGroup === [])
                <p class="py-6 text-center text-sm text-muted">{{ __('No one has confirmed yet.') }}</p>
            @else
                <ul x-show="! table" class="space-y-3">
                    @foreach ($byGroup as $groupName => $people)
                        <li class="grid grid-cols-[7rem_minmax(0,1fr)] items-center gap-3 text-sm" title="{{ $groupName }}: {{ Number::format($people) }}">
                            <span class="truncate text-ink-soft">{{ $groupName }}</span>
                            <span class="flex items-center gap-2">
                                <span class="h-5 rounded-r" style="width: {{ max(2, $people / $byGroupMax * 85) }}%; background: var(--viz-sequential);"></span>
                                <span class="tabular-nums text-ink">{{ Number::format($people) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
                <table x-cloak x-show="table" class="w-full text-sm">
                    <tbody class="divide-y divide-line">
                        @foreach ($byGroup as $groupName => $people)
                            <tr><td class="py-1.5 text-ink-soft">{{ $groupName }}</td><td class="py-1.5 text-right tabular-nums text-ink">{{ Number::format($people) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    </div>

    {{-- RSVP over time: one series → single-hue columns, no legend --}}
    <x-ui.card :title="__('Responses over the last 30 days')" x-data="{ table: false, tip: null }">
        <x-slot:actions>
            <button type="button" x-on:click="table = ! table" class="text-xs font-medium text-sage hover:text-ink" x-text="table ? @js(__('Show chart')) : @js(__('Show table'))">{{ __('Show table') }}</button>
        </x-slot:actions>

        <div x-show="! table">
            <div class="flex h-32 items-end gap-[2px] border-b" style="border-color: var(--viz-grid);" role="img" aria-label="{{ __('Responses per day') }}">
                @foreach ($perDay as $date => $count)
                    <div class="flex h-full flex-1 items-end" x-on:mouseenter="tip = @js(\Carbon\CarbonImmutable::parse($date)->translatedFormat('d M').' · '.trans_choice(':count response|:count responses', $count))" x-on:mouseleave="tip = null">
                        <div class="w-full max-w-[24px] rounded-t mx-auto" style="height: {{ $count ? max(4, $count / $perDayMax * 100) : 0 }}%; background: var(--viz-sequential);"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-[11px] text-muted">
                <span>{{ \Carbon\CarbonImmutable::parse(array_key_first($perDay))->translatedFormat('d M') }}</span>
                <span x-text="tip" class="text-ink-soft" aria-live="polite"></span>
                <span>{{ __('Today') }}</span>
            </div>
            <p class="mt-1 text-xs text-muted">{{ __('Peak: :count per day · times in :tz', ['count' => $perDayMax, 'tz' => $timezone]) }}</p>
        </div>
        <div x-cloak x-show="table" class="max-h-64 overflow-y-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @foreach (array_reverse($perDay, true) as $date => $count)
                        <tr><td class="py-1.5 text-ink-soft">{{ \Carbon\CarbonImmutable::parse($date)->translatedFormat('d M Y') }}</td><td class="py-1.5 text-right tabular-nums text-ink">{{ $count }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    {{-- Responses --}}
    <x-ui.card :title="__('Responses')" padding="p-0">
        <div class="flex flex-wrap gap-2 border-b border-line p-4">
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by name') }}" aria-label="{{ __('Search responses') }}"
                class="h-9 min-w-[12rem] flex-1 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
            <select wire:model.live="attendance" aria-label="{{ __('Filter by attendance') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                <option value="">{{ __('All answers') }}</option>
                @foreach ($attendanceOptions as $option)
                    <option value="{{ $option->value }}">{{ __($option->label()) }}</option>
                @endforeach
            </select>
        </div>

        @if ($responses->isEmpty())
            <x-ui.empty-state icon="fa-regular fa-envelope-open" :title="__('No responses yet.')" :description="__('Share your invitation and answers will appear here.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($responses as $response)
                    <li wire:key="rsvp-{{ $response->ulid }}" class="flex flex-wrap items-start gap-3 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">
                                {{ $response->name }}
                                @if ($response->guest === null)
                                    <span class="ml-1 text-xs font-normal text-muted">({{ __('public link') }})</span>
                                @endif
                            </p>
                            @if ($response->message)
                                <p class="mt-1 text-sm text-ink-soft">“{{ $response->message }}”</p>
                            @endif
                            <p class="mt-1 text-xs text-muted">
                                {{ $response->updated_at->timezone($timezone)->translatedFormat('d M Y, H:i') }}
                                @if ($response->meal_preference) · {{ __('Meal: :meal', ['meal' => $response->meal_preference]) }} @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <x-ui.badge :variant="$response->attendance->value === 'attending' ? 'success' : 'danger'">{{ __($response->attendance->label()) }}</x-ui.badge>
                            @if ($response->number_of_guests)
                                <p class="mt-1 text-xs text-muted">{{ trans_choice(':count person|:count people', $response->number_of_guests) }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-line px-4 py-3">{{ $responses->links() }}</div>
        @endif
    </x-ui.card>
</div>
