@props(['area' => 'dashboard', 'title' => null, 'fullWidth' => false])

@php
    $navigation = config("navigation.{$area}", []);
@endphp

<x-layouts.base :title="$title">
    <div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">
        {{-- Mobile drawer backdrop --}}
        <div x-cloak x-show="sidebarOpen" x-transition.opacity x-on:click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-ink/30 lg:hidden"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-line bg-paper transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            x-bind:class="{ 'translate-x-0': sidebarOpen }">
            <div class="flex h-16 items-center justify-between px-5">
                <a href="{{ route('home') }}" class="flex items-center" aria-label="{{ config('app.name') }}"><x-logo height="h-8" /></a>
                @if ($area === 'admin')
                    <x-ui.badge variant="accent">{{ __('Admin') }}</x-ui.badge>
                @endif
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2" aria-label="{{ __('Main navigation') }}">
                @foreach ($navigation as $item)
                    @if ($item['route'])
                        @php($isActive = request()->routeIs($item['active'] ?? $item['route']))
                        <a href="{{ route($item['route']) }}"
                            @class(['nav-item', 'nav-item-active' => $isActive])
                            @if ($isActive) aria-current="page" @endif>
                            <i class="{{ $item['icon'] }} w-4 text-center" aria-hidden="true"></i>
                            {{ __($item['label']) }}
                        </a>
                    @else
                        <span class="nav-item nav-item-disabled" aria-disabled="true">
                            <i class="{{ $item['icon'] }} w-4 text-center" aria-hidden="true"></i>
                            {{ __($item['label']) }}
                            <span class="ml-auto text-[10px] font-semibold uppercase tracking-wide">{{ __('Soon') }}</span>
                        </span>
                    @endif
                @endforeach
            </nav>

            @if ($user->isSuperAdmin())
                <div class="border-t border-line p-3">
                    @if ($area === 'admin')
                        <a href="{{ route('dashboard') }}" class="nav-item"><i class="fa-solid fa-arrow-right-arrow-left w-4 text-center" aria-hidden="true"></i>{{ __('Client dashboard') }}</a>
                    @else
                        <a href="{{ route('admin.dashboard') }}" class="nav-item"><i class="fa-solid fa-shield-halved w-4 text-center" aria-hidden="true"></i>{{ __('Admin panel') }}</a>
                    @endif
                </div>
            @endif
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-paper/90 px-4 backdrop-blur sm:px-6">
                <button type="button" class="rounded-lg p-2 text-ink-soft hover:bg-sage-soft lg:hidden" x-on:click="sidebarOpen = true" aria-label="{{ __('Open navigation') }}">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
                </button>

                <label class="relative hidden max-w-sm flex-1 sm:block">
                    <span class="sr-only">{{ __('Search') }}</span>
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted" aria-hidden="true"></i>
                    <input type="search" placeholder="{{ __('Search…') }}" disabled title="{{ __('Search becomes available once invitations exist.') }}"
                        class="h-9 w-full rounded-lg border-line bg-white pl-8 text-sm placeholder:text-muted/70 disabled:cursor-not-allowed disabled:opacity-70">
                </label>

                <div class="ml-auto flex items-center gap-1">
                    <x-ui.dropdown width="w-72">
                        <x-slot:trigger>
                            <button type="button" class="relative rounded-lg p-2 text-ink-soft hover:bg-sage-soft" aria-label="{{ __('Notifications') }}">
                                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                                @if ($hasUnreadNotifications)
                                    <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-accent"></span>
                                @endif
                            </button>
                        </x-slot:trigger>
                        @forelse ($notifications as $notification)
                            <a href="{{ $notification['url'] }}" class="block px-4 py-2.5 hover:bg-paper">
                                <p class="text-sm font-medium text-ink">{{ $notification['title'] }}</p>
                                <p class="text-xs text-ink-soft">{{ $notification['message'] }}</p>
                                <p class="mt-0.5 text-[11px] text-muted">{{ $notification['time'] }}</p>
                            </a>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-muted">{{ __('You have no new notifications.') }}</p>
                        @endforelse
                        @if ($notifications)
                            <form method="POST" action="{{ route('dashboard.notifications.read') }}" class="border-t border-line">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 text-center text-xs font-medium text-sage hover:bg-paper">{{ __('Mark all as read') }}</button>
                            </form>
                        @endif
                    </x-ui.dropdown>

                    <a href="mailto:{{ config('app.support_email', config('mail.from.address')) }}" class="rounded-lg p-2 text-ink-soft hover:bg-sage-soft" aria-label="{{ __('Help') }}" title="{{ __('Help') }}">
                        <i class="fa-regular fa-circle-question" aria-hidden="true"></i>
                    </a>

                    <x-ui.dropdown>
                        <x-slot:trigger>
                            <button type="button" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 hover:bg-sage-soft">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-sage text-xs font-semibold uppercase text-white">{{ mb_substr($user->name, 0, 1) }}</span>
                                <span class="hidden text-sm font-medium text-ink sm:block">{{ $user->name }}</span>
                                <i class="fa-solid fa-chevron-down text-[10px] text-muted" aria-hidden="true"></i>
                            </button>
                        </x-slot:trigger>
                        <div class="border-b border-line px-4 py-2">
                            <p class="truncate text-sm font-medium text-ink">{{ $user->name }}</p>
                            <p class="truncate text-xs text-muted">{{ $user->email }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-ink-soft hover:bg-paper">{{ __('Settings') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-ink-soft hover:bg-paper">{{ __('Log out') }}</button>
                        </form>
                    </x-ui.dropdown>
                </div>
            </header>

            <main @class(['w-full flex-1', 'mx-auto max-w-6xl px-4 py-8 sm:px-6' => ! $fullWidth])>
                @if ($title)
                    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 class="font-display text-3xl text-ink">{{ $title }}</h1>
                            {{ $subtitle ?? '' }}
                        </div>
                        {{ $actions ?? '' }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
