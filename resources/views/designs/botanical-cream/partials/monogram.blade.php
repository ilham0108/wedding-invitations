{{-- Initials of the names ("Ilham & Aisyah" → I ❦ A) with a leaf between them. $names is the cover title. --}}
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
            <svg viewBox="0 0 24 32" fill="none" aria-hidden="true">
                <g stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 30V6" />
                    <path d="M12 8c-4-1-6-4-5-7 4 1 6 4 5 7Zm0 6c4-1 6-4 5-7-4 1-6 4-5 7Zm0 6c-4-1-6-4-5-7 4 1 6 4 5 7Zm0 6c4-1 6-4 5-7-4 1-6 4-5 7Z" />
                </g>
            </svg>
            <span>{{ $initials[1] }}</span>
        @endif
    </span>
@endif
