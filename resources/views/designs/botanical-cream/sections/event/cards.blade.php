{{-- Every event in its own card. --}}
<div>
    @include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])

    @forelse ($config['events'] as $event)
        <div class="bc-card inv-animate">
            @include('designs.botanical-cream.partials.event')
        </div>
    @empty
        <p class="bc-lead" @animate('events')>{{ __('Event details will be shared soon.') }}</p>
    @endforelse
</div>
