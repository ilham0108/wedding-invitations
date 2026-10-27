{{--
    Background photos that change while the guest scrolls (section field "background_photo").
    One layer per distinct photo; invitation.js shows the photo of the last section that reached
    the middle of the screen (sections without a photo keep the previous one). Photos load when
    first shown, the rest after the page finished loading. $class positions the container.
--}}
@php($photos = collect($invitation->sections)->pluck('config.background_photo')->filter()->unique()->values())

@if ($photos->isNotEmpty())
    <div class="inv-backdrop {{ $class ?? '' }}" data-inv-backdrop-root aria-hidden="true">
        @foreach ($photos as $photo)
            <div class="inv-backdrop__layer" data-photo="{{ $photo }}" data-src="{{ \App\Support\CssUrl::wrap($photo) }}"></div>
        @endforeach
    </div>
@endif
