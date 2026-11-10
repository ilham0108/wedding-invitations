<div class="mg-closing inv-animate">
    @if ($config['photo'])
        <img src="{{ $config['photo'] }}" alt="" loading="lazy" decoding="async" class="mg-closing__photo" @animate('photo')
            style="{{ \App\Support\PhotoStyle::image($config['photo_focus'] ?? null) }}">
    @endif
    @if (filled($config['message']))
        <p @animate('message')>{{ $config['message'] }}</p>
    @endif
    <div class="mg-rule" @animate('heading')></div>
    {{-- One line each, so every line can have its own entrance. --}}
    <p><span class="mg-closing__line" @animate('heading')>{{ $config['heading'] }}</span>@if (filled($config['sign_off'] ?? null))<span class="mg-closing__line" @animate('sign_off')>{{ $config['sign_off'] }}</span>@endif @if (filled($config['signature']))<span class="mg-closing__line" @animate('signature')>{{ $config['signature'] }}</span>@endif</p>
    @if (! empty($config['inviters']))
        <p class="mg-closing__inviters" @animate('inviters')>
            {{ __('Also inviting') }}:
            @foreach ($config['inviters'] as $inviter)
                <br>{{ $inviter }}
            @endforeach
        </p>
    @endif
</div>
