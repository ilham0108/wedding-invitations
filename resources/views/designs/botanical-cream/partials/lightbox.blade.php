{{-- Full-screen photo; needs Alpine state "images", "index" and "zoom" from the surrounding gallery. --}}
<template x-teleport="body">
    <div class="bc-lightbox" x-show="zoom" x-cloak x-transition.opacity x-on:click.self="zoom = false" x-on:keydown.escape.window="zoom = false" role="dialog" aria-modal="true">
        <button type="button" class="bc-lightbox__close" x-on:click="zoom = false" aria-label="{{ __('Close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        @if (count($config['images']) > 1)
            <button type="button" class="bc-lightbox__nav bc-lightbox__nav--prev" x-on:click="index = (index - 1 + images.length) % images.length" aria-label="{{ __('Previous photo') }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="bc-lightbox__nav bc-lightbox__nav--next" x-on:click="index = (index + 1) % images.length" aria-label="{{ __('Next photo') }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        @endif
        <img x-bind:src="images[index]" alt="">
    </div>
</template>
