{{-- All events in one card, separated by a sprig. --}}
<div>
    @include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])

    <div class="bc-card inv-animate">
        @forelse ($config['events'] as $event)
            @if (! $loop->first)
                @include('designs.botanical-cream.partials.divider', ['mark' => 'sprig', 'class' => 'bc-sprig bc-sprig--divider'])
            @endif
            @include('designs.botanical-cream.partials.event')
        @empty
            <p class="bc-lead" @animate('events')>{{ __('Event details will be shared soon.') }}</p>
        @endforelse
    </div>
</div>
