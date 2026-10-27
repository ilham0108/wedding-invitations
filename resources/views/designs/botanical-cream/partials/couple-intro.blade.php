@include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])
@if (filled($config['opening'] ?? null))
    <p class="bc-arabic inv-animate" lang="ar" dir="auto">{{ $config['opening'] }}</p>
@endif
@if (filled($config['intro']))
    <p class="bc-lead inv-animate">{{ $config['intro'] }}</p>
@endif
