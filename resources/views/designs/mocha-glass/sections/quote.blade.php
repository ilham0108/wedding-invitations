<div class="mg-quote inv-animate">
    <div class="mg-quote__mark" aria-hidden="true" @animate('text')>&ldquo;</div>
    @if (filled($config['arabic_text'] ?? null))
        <p class="mg-arabic" lang="ar" dir="rtl" @animate('arabic_text')>{{ $config['arabic_text'] }}</p>
    @endif
    <blockquote @animate('text')>{{ $config['text'] }}</blockquote>
    @if (filled($config['author']))
        <cite @animate('author')>{{ $config['author'] }}</cite>
    @endif
    <div class="mg-quote__mark mg-quote__mark--end" aria-hidden="true" @animate('text')>&rdquo;</div>
</div>
