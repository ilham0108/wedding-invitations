{{-- One person after another; every second portrait sits above the name, as in the original design. --}}
<div>
    @include('designs.mocha-glass.partials.couple-intro')

    @foreach ($config['people'] as $person)
        @if (! $loop->first)
            <div class="mg-rule inv-animate"></div>
        @endif
        <div @class(['mg-person inv-animate', 'mg-person--photo-first' => $loop->even])>
            @include('designs.mocha-glass.partials.person-text')
            @if ($person['photo'])
                @include('invitation.partials.framed-photo', ['src' => $person['photo'], 'alt' => $person['name'] ?? '', 'imageClass' => 'mg-portrait', 'wrapperClass' => 'mg-portrait-shape'])
            @endif
        </div>
    @endforeach
</div>
