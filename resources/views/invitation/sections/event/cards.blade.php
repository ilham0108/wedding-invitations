<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl" @animate('heading')>{{ $config['heading'] }}</h2>

    @if (empty($config['events']))
        <p class="mt-6 inv-muted" @animate('events')>{{ __('Event details will be shared soon.') }}</p>
    @else
        <div @class(['mt-10 grid gap-5', 'sm:grid-cols-2' => count($config['events']) > 1])>
            @foreach ($config['events'] as $event)
                @include('invitation.sections.event._card')
            @endforeach
        </div>
    @endif
</div>
