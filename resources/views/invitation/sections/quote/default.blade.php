<div class="inv-container text-center">
    <i class="fa-solid fa-quote-left text-2xl inv-accent" aria-hidden="true"></i>
    @if (filled($config['arabic_text'] ?? null))
        <p lang="ar" dir="rtl" class="mx-auto mt-6 max-w-2xl text-2xl leading-loose">{{ $config['arabic_text'] }}</p>
    @endif
    <blockquote class="inv-heading mx-auto mt-6 max-w-2xl text-2xl italic sm:text-3xl">{{ $config['text'] }}</blockquote>
    @if (filled($config['author']))
        <p class="mt-6 text-sm tracking-wide inv-muted">— {{ $config['author'] }}</p>
    @endif
</div>
