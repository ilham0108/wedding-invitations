<div class="inv-container">
    <h2 class="inv-heading text-center text-4xl" @animate('heading')>{{ $config['heading'] }}</h2>

    @if (empty($config['images']))
        <p class="mt-6 text-center inv-muted" @animate('images')>{{ __('Photos will be added soon.') }}</p>
    @else
        <div class="mt-10">
            @include('invitation.partials.gallery-collage')
        </div>
    @endif
</div>
