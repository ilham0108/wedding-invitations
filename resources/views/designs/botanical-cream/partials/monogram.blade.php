{{-- Initials of the names ("Ilham & Aisyah" → I ♡ A) with a line-art heart between them. $names is the cover title. --}}
@php
    $initials = collect(preg_split('/\s*(?:&|\+|,|\bdan\b|\band\b)\s*/iu', (string) $names))
        ->map(fn (string $name): string => mb_strtoupper(mb_substr(trim($name), 0, 1)))
        ->filter()
        ->take(2)
        ->values();
@endphp

@if ($initials->isNotEmpty())
    <span class="{{ $class ?? 'bc-monogram' }}">
        <span>{{ $initials[0] }}</span>
        @if ($initials->count() > 1)
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 20.5s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.6a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.2-7.5 10.2Z"
                    stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" />
            </svg>
            <span>{{ $initials[1] }}</span>
        @endif
    </span>
@endif
