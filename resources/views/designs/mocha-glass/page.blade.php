{{--
    Mocha Glass page frame (converted from the "Arina & Bagas" HTML design).
    Desktop: photo panel on the left, invitation column on the right. The cover is a
    full-screen layer that slides up when the guest opens the invitation.
--}}
@php
    $sections = collect($invitation->sections);
    $cover = $sections->firstWhere('type', 'cover');
    $photo = $cover['config']['background_image'] ?? null;
    $names = $cover ? ($cover['config']['title'] ?: $invitation->title) : $invitation->title;
    $navIcons = [
        'cover' => ['fa-regular fa-envelope-open', __('Home')],
        'couple' => ['fa-regular fa-heart', __('Couple / Host')],
        'event' => ['fa-regular fa-calendar', __('Event')],
        'gallery' => ['fa-regular fa-images', __('Gallery')],
        'gift' => ['fa-solid fa-gift', __('Digital Gift')],
        'wishes' => ['fa-regular fa-comment-dots', __('Wishes')],
    ];
    // Desktop side panel: its own content when set, otherwise the cover's.
    $panel = $cover['config'] ?? [];
    $panelPhoto = ($panel['desktop_image'] ?? null) ?: $photo;
    $panelSubtitle = filled($panel['desktop_subtitle'] ?? null) ? $panel['desktop_subtitle'] : ($panel['subtitle'] ?? '');
    $panelTitle = filled($panel['desktop_title'] ?? null) ? $panel['desktop_title'] : $names;
    $navItems = $sections->filter(fn (array $section): bool => isset($navIcons[$section['type']]))->unique('type')->take(5);
@endphp

<div data-mg-root @if ($cover) data-mg-locked @endif
    @class([
        'mg',
        'mg--no-overlay' => $cover && ! $cover['config']['overlay'],
        'mg--align-left' => $cover && ($cover['config']['alignment'] ?? 'center') === 'left',
    ])>
    <aside class="mg-hero mg-hero--text-{{ $panel['desktop_text_position'] ?? 'center' }}" aria-hidden="true">
        <div @class(['mg-photo', 'mg-kenburns' => ($panel['background_motion'] ?? 'none') === 'none', 'inv-bg-motion--'.($panel['background_motion'] ?? 'none')]) @if ($panelPhoto) style="background-image: {{ \App\Support\CssUrl::wrap($panelPhoto) }}" @endif></div>
        <span class="mg-frame mg-frame--top"></span>
        <div class="mg-hero__text">
            @if (filled($panelSubtitle))
                <small>{{ $panelSubtitle }}</small>
            @endif
            <p class="mg-display">{{ $panelTitle }}</p>
            @if (filled($panel['desktop_text'] ?? null))
                <p class="mg-hero__body">{{ $panel['desktop_text'] }}</p>
            @endif
        </div>
        <span class="mg-frame mg-frame--bottom"></span>
        @include('invitation.partials.ornaments', ['ornaments' => $cover['desktop_ornaments'] ?? []])
    </aside>

    @if ($cover)
        <div class="mg-cover" data-mg-cover>
            <div class="mg-photo inv-bg-motion--{{ $cover['config']['background_motion'] }}" @if ($photo) style="background-image: {{ \App\Support\CssUrl::wrap($photo) }}" @endif></div>
            @if (filled($cover['config']['subtitle']))
                <span class="mg-tag">{{ $cover['config']['subtitle'] }}</span>
            @endif
            @if ($cover['variant'] === 'framed')
                <div class="mg-framebox mg-framebox--{{ $cover['config']['frame_style'] }}">
            @endif
            <h1 class="mg-names">@include('designs.mocha-glass.partials.names', ['names' => $names])</h1>
            @if (filled($cover['config']['date_text']))
                <p class="mg-date">{{ $cover['config']['date_text'] }}</p>
            @endif
            <button type="button" class="mg-btn-open" data-mg-open>
                <i class="fa-regular fa-envelope-open" aria-hidden="true"></i>{{ $cover['config']['button_label'] ?: __('Open Invitation') }}
            </button>
            @if ($cover['variant'] === 'framed')
                </div>
            @endif
            @include('designs.mocha-glass.partials.guest')
            @include('invitation.partials.ornaments', ['ornaments' => $cover['ornaments']])
        </div>
    @endif

    <main class="mg-main">
        {{-- The cover photo stays behind the content until a section brings its own background photo. --}}
        <div class="mg-main__bg mg-photo" @if ($photo) style="background-image: {{ \App\Support\CssUrl::wrap($photo) }}" @endif>
            @include('invitation.partials.scroll-backdrop')
        </div>

        <div class="mg-content">
            @forelse ($sections as $section)
                @if ($section['native'])
                    @include('invitation.partials.section', ['sectionClass' => 'mg-section mg-section--'.$section['type'].' mg-bg-'.$section['background'], 'classic' => false, 'solidTypes' => ['quote']])
                @else
                    {{-- Sections without a Mocha Glass view keep their classic layout inside a glass card. --}}
                    <section id="section-{{ $section['key'] }}" data-section="{{ $section['type'] }}"
                        @include('invitation.partials.backdrop-attributes')
                        @class(['mg-section mg-bg-'.$section['background'], 'inv-has-ornaments' => ! empty($section['ornaments'])])>
                        <div class="inv-section-body mg-card mg-classic inv-animate">
                            @include($section['view'], ['config' => $section['config'], 'variant' => $section['variant']])
                        </div>
                        @include('invitation.partials.ornaments', ['ornaments' => $section['ornaments']])
                    </section>
                @endif
            @empty
                <p class="mg-section mg-lead">{{ __('This invitation has no visible sections yet.') }}</p>
            @endforelse

            @if ($showBranding)
                <p class="mg-branding">{{ __('Made with') }} <a href="{{ route('home') }}">{{ config('app.name') }}</a></p>
            @endif
        </div>
    </main>

    @if ($navItems->count() > 1)
        <nav class="mg-nav" aria-label="{{ __('Invitation menu') }}">
            @foreach ($navItems as $item)
                <a href="#section-{{ $item['key'] }}" data-mg-nav aria-label="{{ $navIcons[$item['type']][1] }}" @class(['is-active' => $loop->first])>
                    <i class="{{ $navIcons[$item['type']][0] }}" aria-hidden="true"></i>
                </a>
            @endforeach
        </nav>
    @endif

    <canvas class="mg-particles" data-mg-particles aria-hidden="true"></canvas>
</div>
