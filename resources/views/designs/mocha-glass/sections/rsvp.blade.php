@php($rsvp = $invitation->interaction['rsvp'] ?? ['open' => false, 'action' => null, 'max_guests' => 1, 'meal_options' => [], 'existing' => null])

{{-- The form opens in a modal; it reopens by itself when the server sent validation errors back. --}}
<div class="mg-card mg-center inv-animate" id="rsvp-form" x-data="{ open: @js($errors->rsvp->any()) }">
    <h2 class="mg-title">{{ $config['heading'] }}</h2>
    @if (filled($config['description']))
        <p class="mg-lead">{{ $config['description'] }}</p>
    @endif
    @if ($config['deadline_label'] && $rsvp['open'])
        <p class="mg-small">{{ __('Please respond by :date.', ['date' => $config['deadline_label']]) }}</p>
    @endif

    @if (session('rsvp_saved'))
        <p class="mg-status" role="status"><i class="fa-regular fa-circle-check" aria-hidden="true"></i> {{ __('Thank you! Your response has been saved.') }}</p>
    @elseif ($rsvp['existing'])
        <p class="mg-small">{{ __('You replied: :answer', ['answer' => __($rsvp['existing']['attendance_label'])]) }}</p>
    @endif

    @if (! $rsvp['open'])
        <p class="mg-status">{{ $config['closed_message'] }}</p>
    @else
        <button type="button" class="mg-btn-white" x-on:click="open = true">
            <i class="fa-regular fa-circle-check" aria-hidden="true"></i>{{ $rsvp['existing'] ? __('Change my answer') : __('Confirm attendance') }}
        </button>

        <template x-teleport="body">
            <div class="mg-modal" x-show="open" x-cloak x-transition.opacity x-on:click.self="open = false" x-on:keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-label="{{ $config['heading'] }}">
                <div class="mg-modal__box">
                    <button type="button" class="mg-modal__close" x-on:click="open = false" aria-label="{{ __('Close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    <h3 class="mg-modal__title">{{ $config['heading'] }}</h3>
                    @include('invitation.partials.rsvp-form', ['formClass' => 'mg-form'])
                </div>
            </div>
        </template>
    @endif
</div>
