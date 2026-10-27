<div class="mg-card inv-animate">
    <h2 class="mg-title mg-left">{{ $config['heading'] }}</h2>

    @forelse ($config['events'] as $event)
        @include('designs.mocha-glass.partials.event')
    @empty
        <p class="mg-lead mg-left">{{ __('Event details will be shared soon.') }}</p>
    @endforelse
</div>
