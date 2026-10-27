<div class="mg-quote inv-animate">
    <div class="mg-quote__mark" aria-hidden="true">&ldquo;</div>
    @if (filled($config['arabic_text'] ?? null))
        <p class="mg-arabic" lang="ar" dir="rtl">{{ $config['arabic_text'] }}</p>
    @endif
    <blockquote>{{ $config['text'] }}</blockquote>
    @if (filled($config['author']))
        <cite>{{ $config['author'] }}</cite>
    @endif
    <div class="mg-quote__mark mg-quote__mark--end" aria-hidden="true">&rdquo;</div>
</div>
