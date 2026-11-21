{{-- Collage of prints (invitation.partials.gallery-collage) in the glass card; a print opens the lightbox. --}}
<div class="mg-card mg-gallery inv-animate" x-data="{ images: @js($config['images']), index: 0, zoom: false }">
    <h2 class="mg-title" @animate('heading')>{{ $config['heading'] }}</h2>

    @if (empty($config['images']))
        <p class="mg-lead" @animate('images')>{{ __('Photos will be added soon.') }}</p>
    @else
        @include('invitation.partials.gallery-collage', ['zoomable' => true])
        @include('designs.mocha-glass.partials.lightbox')
    @endif
</div>
