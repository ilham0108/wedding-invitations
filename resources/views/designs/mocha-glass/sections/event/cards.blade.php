{{-- Every event in its own glass card. --}}
<div>
    <h2 class="mg-title inv-animate" @animate('heading')>{{ $config['heading'] }}</h2>

    @forelse ($config['events'] as $event)
        <div class="mg-card mg-card--event inv-animate">
            @include('designs.mocha-glass.partials.event')
        </div>
    @empty
        <p class="mg-lead" @animate('events')>{{ __('Event details will be shared soon.') }}</p>
    @endforelse
</div>
