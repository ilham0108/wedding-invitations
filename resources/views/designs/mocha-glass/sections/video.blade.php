<div class="mg-card inv-animate">
    <h2 class="mg-title">{{ $config['heading'] }}</h2>
    @if ($config['embed_url'])
        <div class="mg-video">
            <iframe src="{{ $config['embed_url'] }}" title="{{ $config['heading'] }}" loading="lazy" allowfullscreen
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    @else
        <p class="mg-lead">{{ __('The video will be added soon.') }}</p>
    @endif
</div>
