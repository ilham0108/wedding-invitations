{{-- Name, role, parents and social links of one person ($person). --}}
<div class="mg-person__text">
    @if (filled($person['role'] ?? null))
        <p class="mg-person__role">{{ $person['role'] }}</p>
    @endif
    <h3>{{ $person['name'] ?? '' }}</h3>
    @if (filled($person['parents'] ?? null))
        <p class="mg-person__parents">{{ $person['parents'] }}</p>
    @endif
    @if (filled($person['description'] ?? null))
        <p class="mg-person__parents">{{ $person['description'] }}</p>
    @endif
    @include('invitation.partials.social-links', ['links' => $person['socials'] ?? [], 'wrapperClass' => 'mg-socials', 'class' => 'mg-socials__link'])
</div>
