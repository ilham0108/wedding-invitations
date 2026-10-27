@props(['invitation'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $invitation->meta['title'] }}</title>
    <meta name="description" content="{{ $invitation->meta['description'] }}">
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
    data-inv-desktop="{{ $invitation->pageView ? 'design' : $invitation->decoration['desktop_layout'] }}">
    {{ $slot }}
</body>

</html>
