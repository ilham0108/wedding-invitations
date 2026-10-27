@props(['title' => null, 'description' => null])

{{-- $siteContact, $hasFaq and $footerPages come from the admin CMS (view composer in AppServiceProvider). --}}
@php
    $menu = array_filter([
        '' => __('Home'),
        'features' => __('Features'),
        'templates' => __('Templates'),
        'pricing' => __('Pricing'),
        'how-it-works' => __('How it works'),
        'faq' => $hasFaq ? __('FAQ') : null,
        'contact' => __('Contact'),
    ]);
    $socials = array_filter([
        'instagram' => ['fa-brands fa-instagram', 'Instagram'],
        'facebook' => ['fa-brands fa-facebook', 'Facebook'],
        'tiktok' => ['fa-brands fa-tiktok', 'TikTok'],
        'youtube' => ['fa-brands fa-youtube', 'YouTube'],
    ], fn (string $key): bool => filled($siteContact[$key] ?? null), ARRAY_FILTER_USE_KEY);
    $whatsapp = preg_replace('/\D+/', '', (string) ($siteContact['whatsapp'] ?? ''));
    $onHome = request()->routeIs('home');
    $link = fn (string $anchor): string => $anchor === '' ? route('home') : route('home').'#'.$anchor;
@endphp

<x-layouts.base :title="$title" body-class="bg-cream">
    <x-slot:head>
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:500,500i,600&display=swap" rel="stylesheet">
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
    </x-slot:head>

    <header class="sticky top-0 z-40 border-b border-olive-line/60 bg-cream/85 backdrop-blur-md" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
        <div class="mx-auto flex h-[4.5rem] max-w-6xl items-center justify-between gap-6 px-4 sm:px-6">
            <a href="{{ route('home') }}" aria-label="{{ config('app.name') }}"><x-marketing.brand /></a>

            <nav class="hidden items-center gap-7 text-[0.8rem] text-ink-soft lg:flex" aria-label="{{ __('Main navigation') }}">
                @foreach ($menu as $anchor => $label)
                    <a href="{{ $link($anchor) }}" @class([
                        'border-b py-1 transition hover:text-ink',
                        'border-ink text-ink' => $anchor === '' && $onHome,
                        'border-transparent' => ! ($anchor === '' && $onHome),
                    ])>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ auth()->user()->isSuperAdmin() ? route('admin.dashboard') : route('dashboard') }}" class="inline-flex h-10 items-center rounded-full bg-olive px-5 text-[0.8rem] font-medium text-white transition hover:bg-olive-dark">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden text-[0.8rem] text-ink-soft hover:text-ink sm:inline">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="inline-flex h-10 items-center whitespace-nowrap rounded-full bg-olive px-4 text-[0.8rem] font-medium text-white transition hover:bg-olive-dark sm:px-5">{{ __('Create Invitation') }}</a>
                @endauth
                <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-ink lg:hidden" x-on:click="open = ! open"
                    x-bind:aria-expanded="open" aria-label="{{ __('Menu') }}">
                    <i class="fa-solid" x-bind:class="open ? 'fa-xmark' : 'fa-bars'" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <nav x-show="open" x-cloak x-transition.opacity.duration.150ms class="border-t border-olive-line/60 bg-cream px-4 py-3 lg:hidden" aria-label="{{ __('Main navigation') }}">
            @foreach ($menu as $anchor => $label)
                <a href="{{ $link($anchor) }}" x-on:click="open = false" class="block rounded-lg px-3 py-2.5 text-ink-soft hover:bg-olive-soft hover:text-ink">{{ $label }}</a>
            @endforeach
            @guest
                <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2.5 font-medium text-ink hover:bg-olive-soft">{{ __('Log in') }}</a>
            @endguest
        </nav>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer id="contact" class="scroll-mt-24 border-t border-olive-line/60 bg-cream">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 text-sm sm:px-6 md:grid-cols-[1.5fr_1fr_1fr_1fr]">
            <div class="space-y-4">
                <x-marketing.brand />
                @if (filled($siteContact['tagline'] ?? null))
                    <p class="max-w-xs leading-relaxed text-muted">{{ $siteContact['tagline'] }}</p>
                @endif
                @if (filled($siteContact['email'] ?? null) || $whatsapp !== '' || filled($siteContact['address'] ?? null))
                    <div class="space-y-1.5 text-ink-soft">
                        @if (filled($siteContact['email'] ?? null))
                            <a href="mailto:{{ $siteContact['email'] }}" class="flex items-center gap-2 hover:text-ink"><i class="fa-regular fa-envelope w-4 text-olive" aria-hidden="true"></i>{{ $siteContact['email'] }}</a>
                        @endif
                        @if ($whatsapp !== '')
                            <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-ink"><i class="fa-brands fa-whatsapp w-4 text-olive" aria-hidden="true"></i>{{ $siteContact['whatsapp'] }}</a>
                        @endif
                        @if (filled($siteContact['address'] ?? null))
                            <p class="flex items-start gap-2"><i class="fa-solid fa-location-dot mt-0.5 w-4 text-olive" aria-hidden="true"></i>{{ $siteContact['address'] }}</p>
                        @endif
                    </div>
                @endif
            </div>

            <nav class="space-y-2.5" aria-label="{{ __('Menu') }}">
                <p class="font-semibold text-ink">{{ __('Menu') }}</p>
                @foreach ($menu as $anchor => $label)
                    <a href="{{ $link($anchor) }}" class="block text-muted hover:text-ink">{{ $label }}</a>
                @endforeach
            </nav>

            <nav class="space-y-2.5" aria-label="{{ __('More') }}">
                <p class="font-semibold text-ink">{{ __('More') }}</p>
                @forelse ($footerPages as $footerPage)
                    <a href="{{ route('pages.show', $footerPage) }}" class="block text-muted hover:text-ink">{{ $footerPage->title }}</a>
                @empty
                    <a href="{{ route('login') }}" class="block text-muted hover:text-ink">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="block text-muted hover:text-ink">{{ __('Create Invitation') }}</a>
                @endforelse
            </nav>

            <div class="flex flex-col justify-between gap-6">
                @if ($socials)
                    <div class="space-y-3">
                        <p class="font-semibold text-ink">{{ __('Follow us') }}</p>
                        <div class="flex gap-4 text-lg text-ink-soft">
                            @foreach ($socials as $key => [$icon, $label])
                                <a href="{{ $siteContact[$key] }}" target="_blank" rel="noopener" class="hover:text-olive" aria-label="{{ $label }}"><i class="{{ $icon }}" aria-hidden="true"></i></a>
                            @endforeach
                        </div>
                    </div>
                @endif
                <p class="text-xs text-muted md:text-right">&copy; {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
            </div>
        </div>
    </footer>
</x-layouts.base>
