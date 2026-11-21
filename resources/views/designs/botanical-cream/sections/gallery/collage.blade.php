{{-- Collage of prints (invitation.partials.gallery-collage); six show until "See all", a print opens the lightbox. --}}
<div x-data="{ images: @js($config['images']), index: 0, zoom: false, all: false }">
    @include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])

    @if (empty($config['images']))
        <p class="bc-lead" @animate('images')>{{ __('Photos will be added soon.') }}</p>
    @else
        @include('invitation.partials.gallery-collage', ['zoomable' => true, 'limit' => 6])
        @include('designs.botanical-cream.partials.gallery-more', ['limit' => 6])
        @include('designs.botanical-cream.partials.lightbox')
    @endif
</div>
