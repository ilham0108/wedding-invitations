<div class="mg-card mg-center inv-animate">
    <h2 class="mg-title" @animate('heading')>{{ $config['heading'] }}</h2>
    @if (filled($config['description']))
        <p class="mg-lead" @animate('description')>{{ $config['description'] }}</p>
    @endif
    @if (! empty($config['items']))
        <ul class="mg-protocol">
            @foreach ($config['items'] as $item)
                <li @animate('items', $loop->index)>
                    <span class="mg-protocol__icon"><i class="{{ $item['icon_class'] }}" aria-hidden="true"></i></span>
                    {{ $item['text'] ?? '' }}
                </li>
            @endforeach
        </ul>
    @endif
</div>
