{{-- Section heading with the botanical sprig below it; nothing when the heading is empty. $animateAs: its @animate element. --}}
@if (filled($text ?? null))
    <div class="bc-heading inv-animate" @animate($animateAs ?? 'heading')>
        <h2 class="bc-title">{{ $text }}</h2>
        @include('designs.botanical-cream.partials.sprig')
    </div>
@endif
