{{--
    Floating section menu (App\Services\Invitation\NavigationMenu, resources/js/invitation-nav.js), shared by every design.
    It floats over the page instead of taking room, and stays hidden while the cover is on screen, so it never covers
    the open button. The script shows it, marks the current section and scrolls (or turns the page) on a click.
--}}
@if ($invitation->nav !== [])
    @php($startsHidden = ($invitation->sections[0]['type'] ?? null) === 'cover')
    <nav @class(['inv-nav', 'is-hidden' => $startsHidden]) data-inv-nav aria-label="{{ __('Invitation menu') }}" @if ($startsHidden) inert @endif>
        @foreach ($invitation->nav as $item)
            <a href="#section-{{ $item['key'] }}" class="inv-nav__link" data-inv-nav-link>
                <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                <span class="inv-nav__label">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
@endif
