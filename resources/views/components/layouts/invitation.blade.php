{{-- $static: a still page without JavaScript or motion (template cards, TemplateCardController). --}}
@props(['invitation', 'static' => false])

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
    @unless ($static)
        <script>document.documentElement.classList.add('inv-js')</script>
    @endunless

    {{-- Values are whitelisted/validated by ThemeResolver before reaching this block. --}}
    <style>:root { {!! $invitation->cssVariables !!} }</style>

    @php($uploadedStylesheet = app(\App\Services\Invitation\DesignRegistry::class)->stylesheet($invitation->design))
    @if ($static)
        @vite(['resources/css/invitation.css', ...array_filter($invitation->assets, fn (string $asset): bool => str_ends_with($asset, '.css'))])
    @else
        @vite(['resources/css/invitation.css', 'resources/js/invitation.js', ...$invitation->assets])
    @endif
    @if ($uploadedStylesheet)
        <link rel="stylesheet" href="{{ $uploadedStylesheet }}">
    @endif
</head>

<body class="inv-body inv-btn-{{ $invitation->theme['button_style'] }} inv-design-{{ $invitation->design }} {{ $static ? 'inv-static' : '' }}"
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
    data-inv-scroll="{{ $static ? 'vertical' : ($invitation->decoration['scroll_direction'] ?? 'vertical') }}"
    data-inv-divider-size="{{ $invitation->decoration['divider_size'] ?? 'medium' }}"
    @php($ornamentImages = array_filter([
        '--inv-heading-ornament' => $invitation->decoration['heading_ornament_url'] ?? null,
        '--inv-divider-image' => $invitation->decoration['divider_url'] ?? null,
    ], 'filled'))
    @if ($ornamentImages !== []) style="{{ collect($ornamentImages)->map(fn (string $url, string $name): string => $name.': '.\App\Support\CssUrl::wrap($url))->implode('; ') }}" @endif>
    {{ $slot }}

    @if (! $static && ($invitation->decoration['scroll_direction'] ?? 'vertical') === 'horizontal')
        {{-- Tells guests how to move on; resources/js/page-swipe.js places it and hides it after the first page. --}}
        <p class="inv-page-hint" data-inv-page-hint hidden><span>{{ __('Swipe or scroll to continue') }}</span></p>
    @endif
</body>

</html>
