<div>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-line bg-white px-5 py-4">
        <div>
            <p class="font-medium text-ink">{{ __('Import guests from CSV or Excel') }}</p>
            <p class="text-sm text-muted">
                {{ __('Columns: Name, Phone, Email, Group, Guests.') }}
                <a href="{{ route('dashboard.guests.import-template') }}" class="font-medium text-sage hover:underline">{{ __('Download template') }}</a>
            </p>
        </div>
        <x-ui.upload-progress>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-ink hover:border-ink/30">
                <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                <span wire:loading.remove wire:target="file">{{ __('Choose file') }}</span>
                <span wire:loading wire:target="file">{{ __('Reading file…') }}</span>
                <input type="file" wire:model="file" accept=".csv,.xlsx,.xls,text/csv" class="sr-only">
            </label>
        </x-ui.upload-progress>
    </div>

    @error('file')
        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
    @enderror

    @if ($preview)
        <x-ui.card :title="__('Import preview')" :description="__('Nothing is saved until you confirm.')" class="mt-4">
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-paper p-3">
                    <p class="text-xs text-muted">{{ __('Rows') }}</p>
                    <p class="text-2xl font-semibold text-ink">{{ Number::format($preview['total']) }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50 p-3">
                    <p class="text-xs text-emerald-700">{{ __('Valid') }}</p>
                    <p class="text-2xl font-semibold text-emerald-700">{{ Number::format($preview['valid_count']) }}</p>
                </div>
                <div class="rounded-lg bg-red-50 p-3">
                    <p class="text-xs text-red-700">{{ __('Invalid') }}</p>
                    <p class="text-2xl font-semibold text-red-700">{{ Number::format($preview['invalid_count']) }}</p>
                </div>
            </div>

            @if (($preview['remaining'] ?? null) !== null)
                <p class="mt-4 text-sm text-ink-soft">
                    {{ __('Your plan allows :count more guests in this invitation.', ['count' => Number::format($preview['remaining'])]) }}
                    <a href="{{ route('dashboard.subscription.index') }}" class="font-medium text-sage hover:underline">{{ __('See plans') }}</a>
                </p>
            @endif

            @if ($preview['invalid'])
                <div class="mt-5">
                    <p class="mb-2 text-sm font-medium text-ink">{{ __('Rows that will be skipped') }}</p>
                    <ul class="max-h-56 divide-y divide-line overflow-y-auto rounded-lg border border-line text-sm">
                        @foreach ($preview['invalid'] as $invalid)
                            <li class="px-3 py-2">
                                <span class="font-medium text-ink">{{ __('Row :row', ['row' => $invalid['row']]) }}</span>
                                @if ($invalid['name'] !== '') <span class="text-muted">· {{ $invalid['name'] }}</span> @endif
                                <span class="block text-xs text-red-600">{{ implode(' ', $invalid['errors']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($preview['invalid_count'] > count($preview['invalid']))
                        <p class="mt-1 text-xs text-muted">{{ __('Showing the first :n invalid rows.', ['n' => count($preview['invalid'])]) }}</p>
                    @endif
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-2">
                <x-ui.button type="button" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="button" variant="accent" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" :disabled="$preview['valid_count'] === 0">
                    <span wire:loading.remove wire:target="confirm">{{ __('Import :count guests', ['count' => Number::format($preview['valid_count'])]) }}</span>
                    <span wire:loading wire:target="confirm">{{ __('Importing…') }}</span>
                </x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
