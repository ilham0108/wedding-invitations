<x-layouts.marketing :title="$page->title" :description="$page->meta_description">
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <h1 class="mk-serif text-5xl text-ink">{{ $page->title }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('Last updated :date', ['date' => $page->updated_at->translatedFormat('d F Y')]) }}</p>

        {{-- Markdown rendered by Page::html(): raw HTML stripped, unsafe links removed. --}}
        <div class="cms-prose mt-8">{!! $page->html() !!}</div>
    </article>
</x-layouts.marketing>
