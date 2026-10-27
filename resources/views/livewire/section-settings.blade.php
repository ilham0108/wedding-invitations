<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-display text-xl text-ink">{{ __($label) }}</h2>
        <span class="text-xs text-muted">
            <span wire:loading.remove>@if ($savedAt)<i class="fa-solid fa-check text-emerald-600" aria-hidden="true"></i> {{ __('Saved') }}@endif</span>
            <span wire:loading><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i></span>
        </span>
    </div>

    @if (count($variants) > 1)
        <div>
            <p class="mb-2 text-sm font-medium text-ink">{{ __('Layout') }}</p>
            <div class="flex rounded-lg border border-line p-0.5">
                @foreach ($variants as $option)
                    <button type="button" wire:click="$set('variant', '{{ $option }}')"
                        @class(['flex-1 rounded-md px-2 py-1.5 text-xs capitalize', 'bg-ink text-white' => $variant === $option, 'text-ink-soft hover:bg-paper' => $variant !== $option])>{{ __(str_replace('-', ' ', $option)) }}</button>
                @endforeach
            </div>
        </div>
    @endif

    @foreach ($fields as $field)
        @include('livewire.partials.section-field', ['field' => $field, 'path' => $field['key']])
    @endforeach

    @include('livewire.partials.illustration-picker')
</div>
