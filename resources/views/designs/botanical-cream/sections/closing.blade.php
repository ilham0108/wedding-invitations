{{--
    "Thank you" card: the photo (field "photo") fades into the card, then a diamond divider, the title in
    italic, the message, the line before the signature (e.g. "Hormat kami,") and the names.
--}}
<div @class(['bc-closing inv-animate', 'bc-closing--photo' => $config['photo']])>
    @if ($config['photo'])
        <div class="bc-closing__photo" @animate('photo')>
            <img src="{{ $config['photo'] }}" alt="" loading="lazy" decoding="async" style="{{ \App\Support\PhotoStyle::image($config['photo_focus'] ?? null) }}">
        </div>
    @endif

    <div class="bc-closing__body">
        @include('designs.botanical-cream.partials.divider', ['mark' => 'diamond'])
        @if (filled($config['heading']))
            <h2 class="bc-closing__title" @animate('heading')>{{ $config['heading'] }}</h2>
            @include('designs.botanical-cream.partials.divider', ['mark' => 'diamond'])
        @endif
        @if (filled($config['message']))
            <p class="bc-closing__message" @animate('message')>{{ $config['message'] }}</p>
        @endif
        @if (! empty($config['inviters']))
            <p class="bc-closing__inviters" @animate('inviters')>
                {{ __('Also inviting') }}:
                @foreach ($config['inviters'] as $inviter)
                    <br>{{ $inviter }}
                @endforeach
            </p>
        @endif
        @if (filled($config['sign_off'] ?? null))
            <p class="bc-closing__sign-off" @animate('sign_off')>{{ $config['sign_off'] }}</p>
        @endif
        @if (filled($config['signature']))
            <p class="bc-closing__signature" @animate('signature')>{{ $config['signature'] }}</p>
        @endif
    </div>
</div>
