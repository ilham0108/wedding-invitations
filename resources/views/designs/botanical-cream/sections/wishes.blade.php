{{-- Wishes form, then the approved wishes three per page with arrows and dots. --}}
@php
    $wishes = $invitation->interaction['wishes'] ?? ['open' => false, 'action' => null, 'messages' => []];
    $pages = array_chunk($wishes['messages'], 3);
@endphp

<div id="wishes-form">
    @include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])

    <div class="bc-center inv-animate">
        <i class="fa-solid fa-quote-left bc-quote-mark" aria-hidden="true"></i>
        @if (filled($config['description']))
            <p class="bc-lead">{{ $config['description'] }}</p>
        @endif
    </div>

    @if (session('wish_saved'))
        <p class="bc-status" role="status">
            {{ session('wish_saved') === 'approved' ? __('Thank you for your wishes!') : __('Thank you! Your wishes will appear once the hosts approve them.') }}
        </p>
    @endif

    @if ($wishes['open'])
        <div class="inv-animate">
            @include('invitation.partials.wishes-form', ['formClass' => 'bc-form'])
        </div>
    @endif

    @if ($pages)
        <div class="bc-wishes inv-animate" x-data="{ page: 0, pages: {{ count($pages) }} }">
            @foreach ($pages as $messages)
                <ul class="bc-wishes__page" @if (! $loop->first) x-show="page === {{ $loop->index }}" x-cloak @else x-show="page === 0" @endif>
                    @foreach ($messages as $message)
                        <li class="bc-wish">
                            <span class="bc-wish__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($message['name'], 0, 1)) }}</span>
                            <div>
                                <b>{{ $message['name'] }}</b>
                                <small>{{ $message['date'] }}</small>
                                <p>{{ $message['message'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endforeach

            @if (count($pages) > 1)
                <div class="bc-pager">
                    <button type="button" class="bc-pager__arrow" x-on:click="page = (page - 1 + pages) % pages" aria-label="{{ __('Previous') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                    <div class="bc-pager__dots">
                        @foreach ($pages as $messages)
                            <button type="button" x-on:click="page = {{ $loop->index }}" x-bind:class="page === {{ $loop->index }} && 'is-active'" aria-label="{{ __('Page :n', ['n' => $loop->iteration]) }}"></button>
                        @endforeach
                    </div>
                    <button type="button" class="bc-pager__arrow" x-on:click="page = (page + 1) % pages" aria-label="{{ __('Next') }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>
            @endif
        </div>
    @elseif (! session('wish_saved'))
        <p class="bc-small bc-center">{{ __('No wishes yet. Be the first!') }}</p>
    @endif
</div>
