{{--
    Wide-screen backdrop of the classic page (decoration "desktop_layout"), hidden on phones.
    "column": the cover photo blurred behind a phone-width invitation.
    "split": a cover panel beside the invitation with its own photo, text and ornaments
    (cover fields desktop_*, empty ones reuse the cover's).
--}}
@php
    $cover = collect($invitation->sections)->firstWhere('type', 'cover');
    $config = $cover['config'] ?? [];
    $isSplit = $layout === 'split';
    $usesPanelPhoto = $isSplit && filled($config['desktop_image'] ?? null);
    $photo = $usesPanelPhoto ? $config['desktop_image'] : ($config['background_image'] ?? null);
    $photoPosition = \App\Support\PhotoStyle::background($usesPanelPhoto ? ($config['desktop_image_focus'] ?? null) : ($config['background_image_focus'] ?? null));
    $subtitle = filled($config['desktop_subtitle'] ?? null) ? $config['desktop_subtitle'] : ($config['subtitle'] ?? '');
    $title = filled($config['desktop_title'] ?? null) ? $config['desktop_title'] : (($config['title'] ?? '') ?: $invitation->title);
@endphp

<aside @class(['inv-aside', 'inv-aside--photo' => filled($photo), 'inv-aside--text-'.($config['desktop_text_position'] ?? 'center') => $isSplit]) aria-hidden="true">
    @if (filled($photo))
        <div class="inv-aside__photo" style="background-image: {{ \App\Support\CssUrl::wrap($photo) }}; {{ $photoPosition }}"></div>
    @endif

    @if ($isSplit)
        <div class="inv-aside__text">
            @if (filled($subtitle))
                <p class="text-xs uppercase tracking-[0.35em]">{{ $subtitle }}</p>
            @endif
            <p class="inv-heading mt-5 text-6xl">{{ $title }}</p>
            @if (filled($config['desktop_text'] ?? null))
                <div class="inv-divider"></div>
                <p class="inv-aside__body">{{ $config['desktop_text'] }}</p>
            @endif
        </div>
        @include('invitation.partials.ornaments', ['ornaments' => $cover['desktop_ornaments'] ?? []])
    @endif
</aside>
