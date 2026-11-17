{{-- Replaces Livewire's default page links, which carried dark: colours this light-only dashboard never uses. --}}
<div>
    <x-ui.pagination :paginator="$paginator" livewire :scroll-to="$scrollTo ?? '[data-pagination-anchor]'" />
</div>
