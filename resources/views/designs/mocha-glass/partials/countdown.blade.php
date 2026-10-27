<div class="mg-countdown" x-data="countdown(@js($invitation->countdownTarget->toIso8601String()))">
    @foreach (['days' => __('Days'), 'hours' => __('Hours'), 'minutes' => __('Minutes'), 'seconds' => __('Seconds')] as $unit => $label)
        <div><b x-text="{{ $unit }}">00</b><span>{{ $label }}</span></div>
    @endforeach
</div>
