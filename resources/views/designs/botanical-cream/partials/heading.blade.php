{{-- Section heading with the botanical sprig below it; nothing when the heading is empty. --}}
@if (filled($text ?? null))
    <div class="bc-heading inv-animate">
        <h2 class="bc-title">{{ $text }}</h2>
        @include('designs.botanical-cream.partials.sprig')
    </div>
@endif
