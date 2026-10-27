{{-- $links: network => safe URL (from InvitationRenderer); $class styles each link. --}}
@php($icons = ['instagram' => 'fa-brands fa-instagram', 'tiktok' => 'fa-brands fa-tiktok', 'facebook' => 'fa-brands fa-facebook-f', 'x' => 'fa-brands fa-x-twitter', 'youtube' => 'fa-brands fa-youtube'])
@php($labels = ['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'x' => 'X', 'youtube' => 'YouTube'])

@if (! empty($links))
    <div class="{{ $wrapperClass ?? 'mt-4 flex justify-center gap-2' }}">
        @foreach ($links as $network => $url)
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $labels[$network] }}" class="{{ $class ?? 'flex h-9 w-9 items-center justify-center rounded-full inv-card text-sm' }}">
                <i class="{{ $icons[$network] }}" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
@endif
