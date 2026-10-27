{{-- People one below the other with a large "&" between them, as in the mockup. --}}
<div>
    @include('designs.botanical-cream.partials.couple-intro')

    <div class="bc-people">
        @foreach ($config['people'] as $person)
            @if (! $loop->first)
                <p class="bc-people__amp inv-animate" aria-hidden="true">&amp;</p>
            @endif
            @include('designs.botanical-cream.partials.person')
        @endforeach
    </div>
</div>
