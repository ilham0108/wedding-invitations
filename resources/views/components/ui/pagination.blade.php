{{--
    Page links for every paginated list. Used by the Livewire and the Laravel pagination views
    (resources/views/vendor/{livewire,pagination}/tailwind.blade.php), so both look the same.
    Livewire mode changes the page with wire:click; otherwise the pages are plain links.
--}}
@props([
    'paginator',
    'livewire' => false,
    // Livewire only: selector of the element brought into view after a page change.
    'scrollTo' => '[data-pagination-anchor]',
])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pageName = $paginator->getPageName();

    // First, last, and the neighbours of the current page. A gap between them becomes "…".
    $pages = collect([1, $current - 1, $current, $current + 1, $last])
        ->filter(fn (int $page): bool => $page >= 1 && $page <= $last)
        ->unique()
        ->sort()
        ->values();

    $scroll = $livewire && $scrollTo !== false
        ? "(\$el.closest('{$scrollTo}') || document.body).scrollIntoView()"
        : null;

    // 44px targets on phones (R-03), 36px from sm up where a pointer is likely.
    $base = 'inline-flex h-11 min-w-11 items-center justify-center rounded-lg px-2 text-sm tabular-nums transition sm:h-9 sm:min-w-9 focus:outline-none focus-visible:ring-2 focus-visible:ring-sage focus-visible:ring-offset-2';
    $idle = $base.' cursor-pointer text-ink-soft hover:bg-sage-soft hover:text-ink active:bg-sage-soft/70 disabled:cursor-wait disabled:opacity-60';
    $active = $base.' bg-sage font-semibold text-white';
    $disabled = $base.' cursor-not-allowed text-muted/40';
@endphp

@if ($paginator->hasPages())
    <nav aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-muted">
            {{ __('Showing :first–:last of :total', [
                'first' => Number::format($paginator->firstItem() ?? 0),
                'last' => Number::format($paginator->lastItem() ?? 0),
                'total' => Number::format($paginator->total()),
            ]) }}
        </p>

        <div class="flex items-center justify-between gap-1 sm:justify-end">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="{{ $disabled }}" aria-disabled="true" aria-label="{{ __('Previous') }}"><i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i></span>
            @elseif ($livewire)
                <button type="button" wire:click="previousPage('{{ $pageName }}')" @if ($scroll) x-on:click="{{ $scroll }}" @endif wire:loading.attr="disabled" class="{{ $idle }}" aria-label="{{ __('Previous') }}"><i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i></button>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $idle }}" aria-label="{{ __('Previous') }}"><i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i></a>
            @endif

            {{-- Phones: one line of text instead of a row of numbers --}}
            <span class="text-sm text-ink-soft sm:hidden">{{ __('Page :current of :last', ['current' => Number::format($current), 'last' => Number::format($last)]) }}</span>

            {{-- Page numbers --}}
            <span class="hidden items-center gap-1 sm:flex">
                @foreach ($pages as $index => $page)
                    @if ($index > 0 && $page - $pages[$index - 1] > 1)
                        <span class="inline-flex h-9 min-w-6 items-end justify-center pb-2 text-sm text-muted" aria-hidden="true">…</span>
                    @endif

                    @if ($page === $current)
                        <span class="{{ $active }}" aria-current="page">{{ Number::format($page) }}</span>
                    @elseif ($livewire)
                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" @if ($scroll) x-on:click="{{ $scroll }}" @endif wire:loading.attr="disabled" class="{{ $idle }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ Number::format($page) }}</button>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="{{ $idle }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ Number::format($page) }}</a>
                    @endif
                @endforeach
            </span>

            {{-- Next --}}
            @if (! $paginator->hasMorePages())
                <span class="{{ $disabled }}" aria-disabled="true" aria-label="{{ __('Next') }}"><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></span>
            @elseif ($livewire)
                <button type="button" wire:click="nextPage('{{ $pageName }}')" @if ($scroll) x-on:click="{{ $scroll }}" @endif wire:loading.attr="disabled" class="{{ $idle }}" aria-label="{{ __('Next') }}"><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></button>
            @else
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $idle }}" aria-label="{{ __('Next') }}"><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></a>
            @endif
        </div>
    </nav>
@endif
