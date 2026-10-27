@include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])

<div class="inv-animate">
    @if ($invitation->countdownTarget)
        <div x-data="countdown(@js($invitation->countdownTarget->toIso8601String()))">
            <div x-show="! expired" class="bc-countdown">
                @foreach (['days' => __('Days'), 'hours' => __('Hours'), 'minutes' => __('Minutes'), 'seconds' => __('Seconds')] as $unit => $label)
                    <div><b x-text="{{ $unit }}">00</b><span>{{ $label }}</span></div>
                @endforeach
            </div>
            <p x-cloak x-show="expired" class="bc-lead">{{ $config['expired_message'] }}</p>
        </div>
    @else
        <p class="bc-lead">{{ __('The date will be announced soon.') }}</p>
    @endif
</div>
