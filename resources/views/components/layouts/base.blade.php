@props(['title' => null, 'bodyClass' => ''])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    {{-- json_encode via {{ }} (not @json: Blade splits @json arguments on commas inside the strings). --}}
    data-notify-i18n="{{ json_encode(['confirm' => __('Yes, continue'), 'confirmDanger' => __('Yes, delete'), 'cancel' => __('Cancel')]) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    <x-favicon />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600|playfair-display:400,500,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>

<body class="{{ $bodyClass }}">
    {{ $slot }}

    <x-ui.toast />
    @livewireScriptConfig
</body>

</html>
