@if (filled($config['opening'] ?? null))
    <p class="mg-arabic mg-center inv-animate" lang="ar" dir="auto" @animate('opening')>{{ $config['opening'] }}</p>
@endif
@if (filled($config['heading']))
    <h2 class="mg-title inv-animate" @animate('heading')>{{ $config['heading'] }}</h2>
@endif
@if (filled($config['intro']))
    <p class="mg-lead inv-animate" @animate('intro')>{{ $config['intro'] }}</p>
@endif
