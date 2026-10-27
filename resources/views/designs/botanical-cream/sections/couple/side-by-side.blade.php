{{-- People next to each other in two columns. --}}
<div>
    @include('designs.botanical-cream.partials.couple-intro')

    <div class="bc-people bc-people--grid">
        @foreach ($config['people'] as $person)
            @include('designs.botanical-cream.partials.person')
        @endforeach
    </div>
</div>
