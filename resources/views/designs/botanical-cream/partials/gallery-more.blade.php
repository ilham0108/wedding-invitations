{{-- "See all photos" for the grid and masonry galleries: the first photos show, the rest after the click. --}}
@if (count($config['images']) > $limit)
    <div class="bc-more" x-show="! all">
        <button type="button" class="bc-btn" x-on:click="all = true">{{ __('See all photos (:count)', ['count' => count($config['images'])]) }}</button>
    </div>
@endif
