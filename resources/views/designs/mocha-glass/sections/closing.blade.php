<div class="mg-closing inv-animate">
    @if ($config['photo'])
        <img src="{{ $config['photo'] }}" alt="" loading="lazy" decoding="async" class="mg-closing__photo"
            style="{{ \App\Support\PhotoStyle::image($config['photo_focus'] ?? null) }}">
    @endif
    @if (filled($config['message']))
        <p>{{ $config['message'] }}</p>
    @endif
    <div class="mg-rule"></div>
    <p>{{ $config['heading'] }}@if (filled($config['sign_off'] ?? null))<br>{{ $config['sign_off'] }}@endif @if (filled($config['signature']))<br>{{ $config['signature'] }}@endif</p>
    @if (! empty($config['inviters']))
        <p class="mg-closing__inviters">
            {{ __('Also inviting') }}:
            @foreach ($config['inviters'] as $inviter)
                <br>{{ $inviter }}
            @endforeach
        </p>
    @endif
</div>
