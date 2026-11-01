@props(['title' => null, 'description' => null])

{{-- $siteContact, $homeSections, $hasFaq and $footerPages come from the admin CMS (view composer in AppServiceProvider). --}}
@php
    $menu = array_filter([
        'demo' => $homeSections['demo'] ? __('Demo') : null,
        'templates' => $homeSections['templates'] ? __('Templates') : null,
        'how-it-works' => $homeSections['how-it-works'] ? __('How it works') : null,
        'features' => $homeSections['features'] ? __('Features') : null,
        'pricing' => $homeSections['pricing'] ? __('Pricing') : null,
        'faq' => $hasFaq ? __('FAQ') : null,
    ]);
    $socials = array_filter([
        'instagram' => ['fa-brands fa-instagram', 'Instagram'],
        'facebook' => ['fa-brands fa-facebook', 'Facebook'],
        'tiktok' => ['fa-brands fa-tiktok', 'TikTok'],
        'youtube' => ['fa-brands fa-youtube', 'YouTube'],
    ], fn (string $key): bool => filled($siteContact[$key] ?? null), ARRAY_FILTER_USE_KEY);
    $whatsapp = preg_replace('/\D+/', '', (string) ($siteContact['whatsapp'] ?? ''));
    $hasContact = filled($siteContact['email'] ?? null) || $whatsapp !== '' || filled($siteContact['address'] ?? null);
    $link = fn (string $anchor): string => route('home').'#'.$anchor;
@endphp

<x-layouts.base :title="$title" body-class="bg-cream">
    <x-slot:head>
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:500,500i,600&display=swap" rel="stylesheet">
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
    </x-slot:head>

    <header class="sticky top-0 z-40 border-b border-olive-line/70 bg-cream/95" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:h-[4.5rem] sm:px-6">
            <a href="{{ route('home') }}" aria-label="{{ config('app.name') }}" class="shrink-0">
                @if ($hasUploadedLogo)
                    <x-logo height="h-10" />
                @else
                    <x-marketing.brand />
                @endif
            </a>

            <nav class="hidden items-center gap-7 text-[0.85rem] text-ink-soft lg:flex" aria-label="{{ __('Main navigation') }}">
                @foreach ($menu as $anchor => $label)
                    <a href="{{ $link($anchor) }}" class="py-2 transition hover:text-ink">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-1 sm:gap-3">
                @auth
                    <a href="{{ auth()->user()->isSuperAdmin() ? route('admin.dashboard') : route('dashboard') }}" class="inline-flex h-11 items-center rounded-full bg-olive px-5 text-[0.85rem] font-medium text-white transition hover:bg-olive-dark">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="hidden py-2 text-[0.85rem] text-ink-soft hover:text-ink sm:inline">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="inline-flex h-11 items-center whitespace-nowrap rounded-full bg-olive px-4 text-[0.85rem] font-medium text-white transition hover:bg-olive-dark sm:px-5">{{ __('Create Invitation') }}</a>
                @endauth
                {{-- Labelled, so the menu is discoverable on phones. --}}
                <button type="button" class="flex h-11 items-center gap-2 rounded-full px-2.5 text-[0.85rem] text-ink transition hover:bg-olive-soft lg:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="mk-mobile-menu">
                    <i class="fa-solid" x-bind:class="open ? 'fa-xmark' : 'fa-bars'" aria-hidden="true"></i>
                    <span class="max-[379px]:sr-only">{{ __('Menu') }}</span>
                </button>
            </div>
        </div>

        <nav id="mk-mobile-menu" x-show="open" x-cloak x-transition.opacity.duration.150ms class="border-t border-olive-line/70 bg-cream px-4 pb-4 pt-2 lg:hidden" aria-label="{{ __('Main navigation') }}">
            @foreach ($menu as $anchor => $label)
                <a href="{{ $link($anchor) }}" x-on:click="open = false" class="block border-b border-olive-line/60 py-3 text-ink-soft hover:text-ink">{{ $label }}</a>
            @endforeach
            @guest
                <a href="{{ route('login') }}" class="block py-3 font-medium text-ink">{{ __('Log in') }}</a>
            @endguest
        </nav>
    </header>

    <main>
        {{ $slot }}
    </main>

    {{-- Footer: who we are and how to reach us, then one line of links. --}}
    <footer id="contact" class="scroll-mt-24 border-t border-olive-line/70 bg-cream">
        <div class="mx-auto max-w-6xl px-4 py-12 text-sm sm:px-6 sm:py-14">
            <div class="grid gap-10 md:grid-cols-[1.2fr_1fr] md:gap-16">
                <div class="space-y-4">
                    @if ($hasUploadedLogo)
                        <x-logo height="h-12" />
                    @else
                        <x-marketing.brand />
                    @endif
                    @if (filled($siteContact['tagline'] ?? null))
                        <p class="max-w-sm leading-relaxed text-ink-soft">{{ $siteContact['tagline'] }}</p>
                    @endif
                </div>

                @if ($hasContact)
                    <div>
                        <p class="mk-serif text-2xl text-ink">{{ __('Questions before you start?') }}</p>
                        <div class="mt-3 space-y-1 text-ink-soft">
                            @if ($whatsapp !== '')
                                <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="flex min-h-11 items-center gap-3 hover:text-ink"><i class="fa-brands fa-whatsapp w-4 text-olive" aria-hidden="true"></i>{{ $siteContact['whatsapp'] }}</a>
                            @endif
                            @if (filled($siteContact['email'] ?? null))
                                <a href="mailto:{{ $siteContact['email'] }}" class="flex min-h-11 items-center gap-3 hover:text-ink"><i class="fa-regular fa-envelope w-4 text-olive" aria-hidden="true"></i>{{ $siteContact['email'] }}</a>
                            @endif
                            @if (filled($siteContact['address'] ?? null))
                                <p class="flex items-start gap-3 py-2"><i class="fa-solid fa-location-dot mt-0.5 w-4 text-olive" aria-hidden="true"></i>{{ $siteContact['address'] }}</p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-10 flex flex-col gap-6 border-t border-olive-line/70 pt-6 md:flex-row md:items-center md:justify-between">
                <nav class="flex flex-wrap gap-x-6 text-ink-soft" aria-label="{{ __('Footer') }}">
                    @foreach ($menu as $anchor => $label)
                        <a href="{{ $link($anchor) }}" class="inline-flex min-h-11 items-center hover:text-ink">{{ $label }}</a>
                    @endforeach
                    @foreach ($footerPages as $footerPage)
                        <a href="{{ route('pages.show', $footerPage) }}" class="inline-flex min-h-11 items-center hover:text-ink">{{ $footerPage->title }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center justify-between gap-6 md:justify-end">
                    @if ($socials)
                        <div class="flex gap-1 text-lg text-ink-soft">
                            @foreach ($socials as $key => [$icon, $label])
                                <a href="{{ $siteContact[$key] }}" target="_blank" rel="noopener" class="flex h-11 w-11 items-center justify-center rounded-full hover:bg-olive-soft hover:text-olive" aria-label="{{ $label }}"><i class="{{ $icon }}" aria-hidden="true"></i></a>
                            @endforeach
                        </div>
                    @endif
                    <p class="text-xs text-ink-soft">&copy; {{ now()->year }} {{ config('app.name') }}</p>
                </div>
            </div>
        </div>
    </footer>
</x-layouts.base>
