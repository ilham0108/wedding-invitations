<div class="mg-card inv-animate">
    <h2 class="mg-title" @animate('heading')>{{ $config['heading'] }}</h2>

    @forelse ($config['events'] as $event)
        @include('designs.mocha-glass.partials.event')
    @empty
        <p class="mg-lead" @animate('events')>{{ __('Event details will be shared soon.') }}</p>
    @endforelse
</div>
