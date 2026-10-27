<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>

    @if ($invitation->countdownTarget)
        <div x-data="countdown(@js($invitation->countdownTarget->toIso8601String()))" class="mt-10">
            <div x-show="! expired" class="mx-auto grid max-w-md grid-cols-4 gap-3">
                @foreach (['days' => __('Days'), 'hours' => __('Hours'), 'minutes' => __('Minutes'), 'seconds' => __('Seconds')] as $unit => $label)
                    <div class="inv-card px-2 py-4">
                        <p class="inv-heading text-3xl tabular-nums sm:text-4xl" x-text="{{ $unit }}">00</p>
                        <p class="mt-1 text-[11px] uppercase tracking-wider inv-muted">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
            <p x-cloak x-show="expired" class="mx-auto max-w-md inv-muted">{{ $config['expired_message'] }}</p>
            <p class="mt-6 text-xs inv-muted">{{ $invitation->countdownTarget->translatedFormat('l, d F Y · H:i') }} ({{ $invitation->timezone }})</p>
        </div>
    @else
        <p class="mt-6 inv-muted">{{ __('The date will be announced soon.') }}</p>
    @endif
</div>
