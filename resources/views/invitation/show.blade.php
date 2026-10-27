<x-layouts.invitation :invitation="$invitation">
    @isset($previewLabel)
        <div class="inv-preview-bar sticky top-0 z-[200] flex items-center justify-between gap-3 bg-black/80 px-4 py-2 text-xs text-white backdrop-blur">
            <span class="truncate"><i class="fa-regular fa-eye mr-1" aria-hidden="true"></i>{{ $previewLabel }}</span>
            <span class="flex shrink-0 gap-3">
                @isset($backUrl)
                    <a href="{{ $backUrl }}" class="underline underline-offset-2">{{ __('Back to editor') }}</a>
                @endisset
                @isset($useTemplateUrl)
                    <a href="{{ $useTemplateUrl }}" class="rounded-full bg-white px-3 py-1 font-medium text-black">{{ __('Use Template') }}</a>
                @endisset
            </span>
        </div>
    @endisset

    @if ($invitation->pageView)
        @include($invitation->pageView, ['showBranding' => $showBranding ?? true])
    @else
        {{-- On wide screens the decoration's desktop layout narrows the page to a column (see invitation.css). --}}
        <div class="inv-page inv-page--{{ $invitation->decoration['desktop_layout'] }}">
            @if ($invitation->decoration['desktop_layout'] !== 'full')
                @include('invitation.partials.desktop-aside', ['layout' => $invitation->decoration['desktop_layout']])
            @endif

            <div class="inv-page__column">
                @include('invitation.partials.scroll-backdrop', ['class' => 'inv-backdrop--page'])

                <main>
                    @forelse ($invitation->sections as $section)
                        @include('invitation.partials.section')
                    @empty
                        <div class="flex min-h-screen items-center justify-center p-8 text-center">
                            <p class="inv-muted">{{ __('This invitation has no visible sections yet.') }}</p>
                        </div>
                    @endforelse
                </main>

                @if ($showBranding ?? true)
                    <footer class="py-8 text-center text-xs inv-muted">
                        {{ __('Made with') }} <a href="{{ route('home') }}" class="underline underline-offset-2">{{ config('app.name') }}</a>
                    </footer>
                @endif
            </div>
        </div>
    @endif

    @if ($invitation->music || isset($story))
        <div class="inv-fabs">
            {{-- Top to bottom: Instagram Story, music. --}}
            @isset($story)
                @include('invitation.partials.story-share')
            @endisset
            @include('invitation.partials.music', ['autoplay' => ! ($embedded ?? false)])
        </div>
    @endif
</x-layouts.invitation>
