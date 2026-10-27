{{-- Verse or quote in small italic serif, as on the mockup's first screen. --}}
<div class="bc-quote inv-animate">
    @if (filled($config['arabic_text'] ?? null))
        <p class="bc-arabic" lang="ar" dir="rtl">{{ $config['arabic_text'] }}</p>
    @endif
    @if (filled($config['text']))
        <blockquote>&ldquo;{{ $config['text'] }}&rdquo;</blockquote>
    @endif
    @if (filled($config['author']))
        <cite>({{ $config['author'] }})</cite>
    @endif
</div>
