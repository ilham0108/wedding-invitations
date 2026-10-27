@php($rsvp = $invitation->interaction['rsvp'] ?? ['open' => false, 'action' => null, 'max_guests' => 1, 'meal_options' => [], 'existing' => null])

<div class="inv-container text-center" id="rsvp-form">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>
    @if (filled($config['description']))
        <p class="mx-auto mt-4 max-w-md inv-muted">{{ $config['description'] }}</p>
    @endif
    @if ($config['deadline_label'] && $rsvp['open'])
        <p class="mt-3 text-sm">{{ __('Please respond by :date.', ['date' => $config['deadline_label']]) }}</p>
    @endif

    @if (session('rsvp_saved'))
        <div class="inv-card mx-auto mt-8 max-w-md px-6 py-5" role="status">
            <i class="fa-regular fa-circle-check inv-accent text-2xl" aria-hidden="true"></i>
            <p class="mt-2 font-medium">{{ __('Thank you! Your response has been saved.') }}</p>
        </div>
    @endif

    @if (! $rsvp['open'])
        <div class="inv-card mx-auto mt-8 max-w-md px-6 py-5 inv-muted">{{ $config['closed_message'] }}</div>
    @else
        @if ($rsvp['existing'] && ! session('rsvp_saved'))
            <p class="mt-6 text-sm">
                {{ __('You replied: :answer', ['answer' => __($rsvp['existing']['attendance_label'])]) }}@if ($rsvp['existing']['number_of_guests']) · {{ trans_choice(':count person|:count people', $rsvp['existing']['number_of_guests']) }}@endif.
                {{ __('You can update your answer below.') }}
            </p>
        @endif

        @include('invitation.partials.rsvp-form')
    @endif
</div>
