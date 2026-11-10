{{-- One person ($person): round portrait (photo_shape / photo_frame apply), name, role, parents, socials. --}}
<div class="bc-person inv-animate" @animate('people', $loop->index ?? 0)>
    @if ($person['photo'])
        @include('invitation.partials.framed-photo', ['src' => $person['photo'], 'focus' => $person['photo_focus'] ?? null, 'alt' => $person['name'] ?? '', 'imageClass' => 'bc-portrait', 'wrapperClass' => 'bc-portrait-shape'])
    @endif
    @include('designs.botanical-cream.partials.person-text')
</div>
