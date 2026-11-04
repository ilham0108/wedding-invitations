{{-- Role, name, parents, description and social links of one person ($person). $socialsClass: the links row. --}}
@if (filled($person['role'] ?? null))
    <p class="text-xs uppercase tracking-[0.3em] inv-accent">{{ $person['role'] }}</p>
@endif
<h3 class="inv-heading mt-2 text-3xl">{{ $person['name'] ?? '' }}</h3>
@if (filled($person['parents'] ?? null))
    <p class="mt-3 text-sm inv-muted">{{ $person['parents'] }}</p>
@endif
@if (filled($person['description'] ?? null))
    <p class="mt-3 text-sm inv-muted">{{ $person['description'] }}</p>
@endif
@include('invitation.partials.social-links', ['links' => $person['socials'] ?? [], 'wrapperClass' => $socialsClass ?? null])
