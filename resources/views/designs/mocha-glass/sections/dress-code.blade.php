@php($details = array_filter([
    'fa-solid fa-user-tie' => ['Men', $config['men'] ?? ''],
    'fa-solid fa-person-dress' => ['Women', $config['women'] ?? ''],
    'fa-solid fa-palette' => ['Colors', $config['color_note'] ?? ''],
    'fa-solid fa-ban' => ['Please avoid', $config['avoid'] ?? ''],
], fn (array $detail): bool => filled($detail[1])))

<div class="mg-card mg-card--tight inv-animate">
    <div class="mg-dresscode">
        <i class="fa-solid fa-ribbon mg-dresscode__ribbon" aria-hidden="true"></i>
        <h2>{{ $config['heading'] }}</h2>
        @if (filled($config['description']))
            <p class="mg-dresscode__sub">{{ $config['description'] }}</p>
        @endif

        @if (! empty($config['colors']))
            <div class="mg-dresscode__palette">
                @foreach ($config['colors'] as $color)
                    <span style="background: {{ $color }};" title="{{ $color }}"></span>
                @endforeach
            </div>
        @endif

        @if ($details)
            <div class="mg-dresscode__grid">
                @foreach ($details as $icon => [$label, $value])
                    <div>
                        <span class="mg-dresscode__icon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                        <b>{{ __($label) }}</b>{{ $value }}
                    </div>
                @endforeach
            </div>
        @endif

        @if (! empty($config['notes']))
            <div class="mg-dresscode__notes">
                <i class="fa-solid fa-square-parking" aria-hidden="true"></i>
                <ul>
                    @foreach ($config['notes'] as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
