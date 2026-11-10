{{-- Verse or quote in small italic serif, as on the mockup's first screen. --}}
<div class="bc-quote inv-animate">
    @if (filled($config['arabic_text'] ?? null))
        <p class="bc-arabic" lang="ar" dir="rtl" @animate('arabic_text')>{{ $config['arabic_text'] }}</p>
    @endif
    @if (filled($config['text']))
        <blockquote @animate('text')>&ldquo;{{ $config['text'] }}&rdquo;</blockquote>
    @endif
    @if (filled($config['author']))
        <cite @animate('author')>({{ $config['author'] }})</cite>
    @endif
</div>
