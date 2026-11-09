{{-- One row per person: the portrait beside the name, on the side chosen in "photo_side" (App\Support\PhotoSide). --}}
<div>
    @include('designs.botanical-cream.partials.couple-intro')

    <div class="bc-people bc-people--beside">
        @foreach ($config['people'] as $person)
            <div class="bc-person inv-animate {{ \App\Support\PhotoSide::classes($person['photo_side'], filled($person['photo'])) }}">
                @if ($person['photo'])
                    @include('invitation.partials.framed-photo', ['src' => $person['photo'], 'focus' => $person['photo_focus'] ?? null, 'alt' => $person['name'] ?? '', 'imageClass' => 'bc-portrait', 'wrapperClass' => 'bc-portrait-shape'])
                @endif
                @include('designs.botanical-cream.partials.person-text')
            </div>
        @endforeach
    </div>
</div>
