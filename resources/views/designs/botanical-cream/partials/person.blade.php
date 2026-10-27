{{-- One person ($person): round portrait (photo_shape / photo_frame apply), name, role, parents, socials. --}}
<div class="bc-person inv-animate">
    @if ($person['photo'])
        @include('invitation.partials.framed-photo', ['src' => $person['photo'], 'focus' => $person['photo_focus'] ?? null, 'alt' => $person['name'] ?? '', 'imageClass' => 'bc-portrait', 'wrapperClass' => 'bc-portrait-shape'])
    @endif
    @if (filled($person['role'] ?? null))
        <p class="bc-person__role">{{ $person['role'] }}</p>
    @endif
    <h3 class="bc-person__name">{{ $person['name'] ?? '' }}</h3>
    @if (filled($person['parents'] ?? null))
        <p class="bc-person__parents">{{ $person['parents'] }}</p>
    @endif
    @if (filled($person['description'] ?? null))
        <p class="bc-person__parents">{{ $person['description'] }}</p>
    @endif
    @include('invitation.partials.social-links', ['links' => $person['socials'] ?? [], 'wrapperClass' => 'bc-socials', 'class' => 'bc-socials__link'])
</div>
