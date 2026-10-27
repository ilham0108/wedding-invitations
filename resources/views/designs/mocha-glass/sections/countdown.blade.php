<div class="mg-card mg-center inv-animate">
    <h2 class="mg-title">{{ $config['heading'] }}</h2>
    @if ($invitation->countdownTarget)
        <div x-data="countdown(@js($invitation->countdownTarget->toIso8601String()))">
            <div x-show="! expired" class="mg-countdown mg-countdown--center">
                @foreach (['days' => __('Days'), 'hours' => __('Hours'), 'minutes' => __('Minutes'), 'seconds' => __('Seconds')] as $unit => $label)
                    <div><b x-text="{{ $unit }}">00</b><span>{{ $label }}</span></div>
                @endforeach
            </div>
            <p x-cloak x-show="expired" class="mg-lead">{{ $config['expired_message'] }}</p>
        </div>
    @else
        <p class="mg-lead">{{ __('The date will be announced soon.') }}</p>
    @endif
</div>
