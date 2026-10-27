@php($wishes = $invitation->interaction['wishes'] ?? ['open' => false, 'action' => null, 'messages' => []])

<div class="inv-container text-center" id="wishes-form">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>
    @if (filled($config['description']))
        <p class="mx-auto mt-4 max-w-md inv-muted">{{ $config['description'] }}</p>
    @endif

    @if (session('wish_saved'))
        <div class="inv-card mx-auto mt-8 max-w-md px-6 py-4 text-sm" role="status">
            {{ session('wish_saved') === 'approved' ? __('Thank you for your wishes!') : __('Thank you! Your wishes will appear once the hosts approve them.') }}
        </div>
    @endif

    @if ($wishes['open'])
        @include('invitation.partials.wishes-form')
    @endif

    @if ($wishes['messages'])
        <ul class="mx-auto mt-8 max-w-md space-y-3 text-left">
            @foreach ($wishes['messages'] as $message)
                <li class="inv-card p-4">
                    <p class="text-sm">{{ $message['message'] }}</p>
                    <p class="mt-2 text-xs inv-muted">— {{ $message['name'] }} · {{ $message['date'] }}</p>
                </li>
            @endforeach
        </ul>
    @elseif (! session('wish_saved'))
        <p class="mt-6 text-sm inv-muted">{{ __('No wishes yet. Be the first!') }}</p>
    @endif
</div>
