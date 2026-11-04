{{-- One row per person: the portrait beside the name, on the side chosen in "photo_side" (App\Support\PhotoSide). --}}
<div>
    @include('designs.mocha-glass.partials.couple-intro')

    <div class="mg-people-beside">
        @foreach ($config['people'] as $person)
            <div class="mg-person inv-animate {{ \App\Support\PhotoSide::classes($config['photo_side'] ?? null, $loop->index, filled($person['photo'])) }}">
                @if ($person['photo'])
                    @include('invitation.partials.framed-photo', ['src' => $person['photo'], 'focus' => $person['photo_focus'] ?? null, 'alt' => $person['name'] ?? '', 'imageClass' => 'mg-portrait', 'wrapperClass' => 'mg-portrait-shape'])
                @endif
                @include('designs.mocha-glass.partials.person-text')
            </div>
        @endforeach
    </div>
</div>
