<div class="mg-card inv-animate">
    <h2 class="mg-title" @animate('heading')>{{ $config['heading'] }}</h2>
    @if ($config['embed_url'])
        <div class="mg-video" @animate('url')>
            <iframe src="{{ $config['embed_url'] }}" title="{{ $config['heading'] }}" loading="lazy" allowfullscreen
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    @else
        <p class="mg-lead" @animate('url')>{{ __('The video will be added soon.') }}</p>
    @endif
</div>
