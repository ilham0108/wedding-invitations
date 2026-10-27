@php($wishes = $invitation->interaction['wishes'] ?? ['open' => false, 'action' => null, 'messages' => []])

<div class="mg-card inv-animate" id="wishes-form">
    <h2 class="mg-title">{{ $config['heading'] }}</h2>
    @if (filled($config['description']))
        <p class="mg-lead">{{ $config['description'] }}</p>
    @endif

    @if (session('wish_saved'))
        <p class="mg-status" role="status">
            {{ session('wish_saved') === 'approved' ? __('Thank you for your wishes!') : __('Thank you! Your wishes will appear once the hosts approve them.') }}
        </p>
    @endif

    @if ($wishes['open'])
        @include('invitation.partials.wishes-form', ['formClass' => 'mg-form mg-form--glass'])
    @endif

    @if ($wishes['messages'])
        <ul class="mg-wishes">
            @foreach ($wishes['messages'] as $message)
                <li class="mg-wish">
                    <b>{{ $message['name'] }}</b>
                    <small><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ $message['date'] }}</small>
                    <p>{{ $message['message'] }}</p>
                </li>
            @endforeach
        </ul>
    @elseif (! session('wish_saved'))
        <p class="mg-small mg-center">{{ __('No wishes yet. Be the first!') }}</p>
    @endif
</div>
