{{--
    "Thank you" card. With a section background photo and the "Utama" background it becomes the
    mockup's photo card: the photo shows through the brown tint (invitation.partials.backdrop-attributes).
--}}
<div class="bc-closing inv-animate">
    @if (filled($config['heading']))
        <h2 class="bc-title">{{ $config['heading'] }}</h2>
    @endif
    @if (filled($config['message']))
        <p>{{ $config['message'] }}</p>
    @endif
    @if (! empty($config['inviters']))
        <p class="bc-closing__inviters">
            {{ __('Also inviting') }}:
            @foreach ($config['inviters'] as $inviter)
                <br>{{ $inviter }}
            @endforeach
        </p>
    @endif
    @if (filled($config['signature']))
        <p class="bc-closing__signature">{{ $config['signature'] }}</p>
    @endif
    @include('designs.botanical-cream.partials.monogram', ['names' => collect($invitation->sections)->firstWhere('type', 'cover')['config']['title'] ?? $invitation->title, 'class' => 'bc-monogram bc-monogram--large'])
</div>
