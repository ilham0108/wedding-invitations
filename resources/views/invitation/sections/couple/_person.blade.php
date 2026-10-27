<div>
    @if ($person['photo'])
        @include('invitation.partials.framed-photo', [
            'src' => $person['photo'],
            'alt' => $person['name'] ?? '',
            'imageClass' => 'mx-auto mb-5 h-40 w-40 object-cover inv-rounded',
            'imageStyle' => 'border-radius: 9999px;',
            'wrapperClass' => filled($config['photo_frame']) ? 'mx-auto mb-5 w-60' : 'mx-auto mb-5 w-40',
        ])
    @endif
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
    @include('invitation.partials.social-links', ['links' => $person['socials'] ?? []])
</div>
