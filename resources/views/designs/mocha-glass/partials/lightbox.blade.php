{{-- Full-screen photo; needs Alpine state "images", "index" and "zoom" from the surrounding gallery. --}}
<template x-teleport="body">
    <div class="mg-lightbox" x-show="zoom" x-cloak x-transition.opacity x-on:click.self="zoom = false" x-on:keydown.escape.window="zoom = false" role="dialog" aria-modal="true">
        <button type="button" class="mg-lightbox__close" x-on:click="zoom = false" aria-label="{{ __('Close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        <img x-bind:src="images[index]" alt="">
    </div>
</template>
