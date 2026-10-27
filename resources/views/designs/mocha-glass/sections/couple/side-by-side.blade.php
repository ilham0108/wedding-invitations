{{-- People next to each other in two columns, portrait on top. --}}
<div>
    @include('designs.mocha-glass.partials.couple-intro')

    <div class="mg-people-grid">
        @foreach ($config['people'] as $person)
            <div class="mg-person mg-person--compact inv-animate">
                @if ($person['photo'])
                    @include('invitation.partials.framed-photo', ['src' => $person['photo'], 'focus' => $person['photo_focus'] ?? null, 'alt' => $person['name'] ?? '', 'imageClass' => 'mg-portrait', 'wrapperClass' => 'mg-portrait-shape'])
                @endif
                @include('designs.mocha-glass.partials.person-text')
            </div>
        @endforeach
    </div>
</div>
