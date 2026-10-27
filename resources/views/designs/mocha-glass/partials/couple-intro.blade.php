@if (filled($config['opening'] ?? null))
    <p class="mg-arabic mg-center inv-animate" lang="ar" dir="auto">{{ $config['opening'] }}</p>
@endif
@if (filled($config['heading']))
    <h2 class="mg-title inv-animate">{{ $config['heading'] }}</h2>
@endif
@if (filled($config['intro']))
    <p class="mg-lead inv-animate">{{ $config['intro'] }}</p>
@endif
