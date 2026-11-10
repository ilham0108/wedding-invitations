@include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])
@if (filled($config['opening'] ?? null))
    <p class="bc-arabic inv-animate" lang="ar" dir="auto" @animate('opening')>{{ $config['opening'] }}</p>
@endif
@if (filled($config['intro']))
    <p class="bc-lead inv-animate" @animate('intro')>{{ $config['intro'] }}</p>
@endif
