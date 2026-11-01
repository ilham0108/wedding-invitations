@props(['invitation'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $invitation->meta['title'] }}</title>
    <meta name="description" content="{{ $invitation->meta['description'] }}">
    <x-favicon />
    <meta name="robots" content="{{ $invitation->meta['indexable'] ? 'index, follow' : 'noindex, nofollow' }}">
    @if ($invitation->meta['canonical'])
        <link rel="canonical" href="{{ $invitation->meta['canonical'] }}">
        <meta property="og:url" content="{{ $invitation->meta['canonical'] }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $invitation->meta['title'] }}">
    <meta property="og:description" content="{{ $invitation->meta['description'] }}">
    @if ($invitation->meta['image'])
        <meta property="og:image" content="{{ url($invitation->meta['image']) }}">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="{{ $invitation->fontStylesheetUrl }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">

    {{-- Entrance animations only hide content once JavaScript is known to run. --}}
    <script>document.documentElement.classList.add('inv-js')</script>

    {{-- Values are whitelisted/validated by ThemeResolver before reaching this block. --}}
    <style>:root { {!! $invitation->cssVariables !!} }</style>

    @vite(['resources/css/invitation.css', 'resources/js/invitation.js', ...$invitation->assets])
</head>

<body class="inv-body inv-btn-{{ $invitation->theme['button_style'] }} inv-design-{{ $invitation->design }}"
    data-inv-animation="{{ $invitation->decoration['animation'] }}" data-inv-speed="{{ $invitation->decoration['animation_speed'] }}"
    data-inv-divider="{{ $invitation->decoration['divider'] }}" data-inv-texture="{{ $invitation->decoration['texture'] }}"
    data-inv-desktop="{{ $invitation->pageView ? 'design' : $invitation->decoration['desktop_layout'] }}"
    {{-- Ornament under section headings: "mask" (built-in, drawn in the secondary color) or "image" (library image as is). --}}
    data-inv-heading-ornament="{{ in_array($invitation->decoration['heading_ornament'], ['design', 'none', 'image'], true) ? $invitation->decoration['heading_ornament'] : 'mask' }}"
    data-inv-heading-ornament-size="{{ $invitation->decoration['heading_ornament_size'] }}"
    data-inv-heading-scale="{{ $invitation->decoration['heading_scale'] }}" data-inv-text-scale="{{ $invitation->decoration['text_scale'] }}"
    {{-- Theme font weights, only when chosen (values come from --inv-heading-weight / --inv-body-weight). --}}
    @if (($invitation->theme['heading_weight'] ?? 'default') !== 'default') data-inv-heading-weight="{{ $invitation->theme['heading_weight'] }}" @endif
    @if (($invitation->theme['body_weight'] ?? 'default') !== 'default') data-inv-body-weight="{{ $invitation->theme['body_weight'] }}" @endif
    @if (filled($invitation->decoration['heading_ornament_url'] ?? null)) style="--inv-heading-ornament: {{ \App\Support\CssUrl::wrap($invitation->decoration['heading_ornament_url']) }}" @endif>
    {{ $slot }}
</body>

</html>
