{{-- Role, name, parents, description and social links of one person ($person). --}}
<div class="bc-person__text">
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
