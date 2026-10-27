{{--
    Botanical Cream page frame (from the "Ilham & Aisyah" mockup).
    Phones: one scrolling column with a monogram top bar, a labelled bottom menu and a full menu.
    Desktop: photo panel on the left (cover fields desktop_*), the invitation column on the right.
--}}
@php
    $sections = collect($invitation->sections);
    $cover = $sections->firstWhere('type', 'cover');
    $names = $cover ? ($cover['config']['title'] ?: $invitation->title) : $invitation->title;
    // Section types that get a menu entry: icon and short label.
    $menuIcons = [
        'cover' => ['fa-solid fa-house', __('Home')],
        'couple' => ['fa-solid fa-user-group', __('Couple')],
        'event' => ['fa-regular fa-calendar', __('Event')],
        'story' => ['fa-regular fa-heart', __('Love Story')],
        'gallery' => ['fa-solid fa-camera-retro', __('Gallery')],
        'video' => ['fa-solid fa-film', __('Video')],
        'rsvp' => ['fa-regular fa-circle-check', __('RSVP')],
        'gift' => ['fa-solid fa-gift', __('Digital Gift')],
        'wishes' => ['fa-regular fa-envelope', __('Wishes')],
        'maps' => ['fa-solid fa-location-dot', __('Location')],
    ];
    $menuItems = $sections->filter(fn (array $section): bool => isset($menuIcons[$section['type']]))->unique('type')->values();
    $navItems = $menuItems->filter(fn (array $section): bool => in_array($section['type'], ['cover', 'couple', 'event', 'gallery', 'wishes'], true))->take(5)->values();
    // Desktop side panel: its own content when set, otherwise the cover's.
    $panel = $cover['config'] ?? [];
    $photo = $panel['background_image'] ?? null;
    $panelPhoto = ($panel['desktop_image'] ?? null) ?: $photo;
    $panelPosition = \App\Support\PhotoStyle::background(filled($panel['desktop_image'] ?? null) ? ($panel['desktop_image_focus'] ?? null) : ($panel['background_image_focus'] ?? null));
    $panelSubtitle = filled($panel['desktop_subtitle'] ?? null) ? $panel['desktop_subtitle'] : ($panel['subtitle'] ?? '');
    $panelTitle = filled($panel['desktop_title'] ?? null) ? $panel['desktop_title'] : $names;
    // Footer: the date of the cover and the calendar link of the first event that offers one.
    $calendarUrl = $sections->where('type', 'event')->pluck('config.events')->flatten(1)->pluck('calendar_url')->filter()->first();
@endphp

<div class="bc" data-bc-root>
    <aside class="bc-hero bc-hero--text-{{ $panel['desktop_text_position'] ?? 'center' }}" aria-hidden="true">
        <div class="bc-hero__photo inv-bg-motion--{{ $panel['background_motion'] ?? 'none' }}" @if ($panelPhoto) style="background-image: {{ \App\Support\CssUrl::wrap($panelPhoto) }}; {{ $panelPosition }}" @endif></div>
        <div class="bc-hero__text">
            @include('designs.botanical-cream.partials.monogram', ['names' => $names, 'class' => 'bc-monogram bc-monogram--large'])
            @if (filled($panelSubtitle))
                <p class="bc-eyebrow">{{ $panelSubtitle }}</p>
            @endif
            <p class="bc-names">@include('designs.botanical-cream.partials.names', ['names' => $panelTitle])</p>
            @if (filled($panel['desktop_text'] ?? null))
                <p class="bc-hero__body">{{ $panel['desktop_text'] }}</p>
            @endif
        </div>
        @include('invitation.partials.ornaments', ['ornaments' => $cover['desktop_ornaments'] ?? []])
    </aside>

    <div class="bc-column">
        <header class="bc-topbar" data-bc-topbar>
            <a href="#section-{{ $cover['key'] ?? '' }}" class="bc-topbar__brand" aria-label="{{ __('Home') }}">
                @include('designs.botanical-cream.partials.monogram', ['names' => $names])
            </a>
            @if ($menuItems->count() > 1)
                <button type="button" class="bc-topbar__menu" data-bc-menu-open aria-controls="bc-menu" aria-expanded="false" aria-label="{{ __('Open menu') }}">
                    <i class="fa-solid fa-bars-staggered" aria-hidden="true"></i>
                </button>
            @endif
        </header>

        {{-- Section background photos (field background_photo) show behind the cream page while their section is on screen. --}}
        <div class="bc-main__bg" aria-hidden="true">
            @include('invitation.partials.scroll-backdrop')
        </div>

        <main class="bc-main">
            @forelse ($sections as $section)
                @if ($section['native'])
                    @include('invitation.partials.section', ['sectionClass' => 'bc-section bc-section--'.$section['type'].' bc-bg-'.$section['background'], 'classic' => false])
                @else
                    {{-- Sections without a Botanical Cream view keep their classic layout inside a cream card. --}}
                    <section id="section-{{ $section['key'] }}" data-section="{{ $section['type'] }}"
                        @include('invitation.partials.backdrop-attributes')
                        @class(['bc-section bc-bg-'.$section['background'], 'inv-has-ornaments' => ! empty($section['ornaments'])])>
                        <div class="inv-section-body bc-card bc-classic inv-animate">
                            @include($section['view'], ['config' => $section['config'], 'variant' => $section['variant']])
                        </div>
                        @include('invitation.partials.ornaments', ['ornaments' => $section['ornaments']])
                    </section>
                @endif
            @empty
                <p class="bc-section bc-lead">{{ __('This invitation has no visible sections yet.') }}</p>
            @endforelse

            <footer class="bc-footer inv-animate">
                <p class="bc-title">{{ __('See you on our happy day') }}</p>
                @if (filled($panel['date_text'] ?? null))
                    <p class="bc-footer__date">{{ $panel['date_text'] }}</p>
                @endif
                @include('designs.botanical-cream.partials.sprig')
                @if ($calendarUrl)
                    <a class="bc-btn" href="{{ $calendarUrl }}" target="_blank" rel="noopener noreferrer"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>{{ __('Save to calendar') }}</a>
                @endif
                @if ($showBranding)
                    <p class="bc-branding">{{ __('Made with') }} <i class="fa-solid fa-heart" aria-hidden="true"></i> <a href="{{ route('home') }}">{{ config('app.name') }}</a></p>
                @endif
            </footer>
        </main>

        @if ($navItems->count() > 1)
            <nav class="bc-nav" aria-label="{{ __('Invitation menu') }}">
                @foreach ($navItems as $item)
                    <a href="#section-{{ $item['key'] }}" data-bc-nav @class(['is-active' => $loop->first])>
                        <i class="{{ $menuIcons[$item['type']][0] }}" aria-hidden="true"></i>
                        <span>{{ $menuIcons[$item['type']][1] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
    </div>

    @if ($menuItems->count() > 1)
        <div class="bc-menu" id="bc-menu" data-bc-menu hidden>
            <div class="bc-menu__panel" role="dialog" aria-modal="true" aria-label="{{ __('Invitation menu') }}">
                <button type="button" class="bc-menu__close" data-bc-menu-close aria-label="{{ __('Close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                <ul>
                    @foreach ($menuItems as $item)
                        <li>
                            <a href="#section-{{ $item['key'] }}" data-bc-menu-link>
                                <i class="{{ $menuIcons[$item['type']][0] }}" aria-hidden="true"></i>{{ $menuIcons[$item['type']][1] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                @include('designs.botanical-cream.partials.monogram', ['names' => $names, 'class' => 'bc-monogram bc-menu__monogram'])
            </div>
        </div>
    @endif
</div>
