<div class="mg-card inv-animate">
    <h2 class="mg-title">{{ $config['heading'] }}</h2>

    @forelse ($config['events'] as $event)
        @include('designs.mocha-glass.partials.event')
    @empty
        <p class="mg-lead">{{ __('Event details will be shared soon.') }}</p>
    @endforelse
</div>
