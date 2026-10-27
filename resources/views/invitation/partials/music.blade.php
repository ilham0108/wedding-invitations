{{--
    Background music (rules.md section 30). Browsers block sound until the guest interacts, so
    invitation.js starts the song on the first tap/click/key (e.g. "Open Invitation"), unless
    $autoplay is false (the editor's live preview). The button pauses and resumes it.
--}}
@if ($invitation->music)
    <div class="inv-music" data-inv-music data-autoplay="{{ ($autoplay ?? true) ? '1' : '0' }}"
        data-start="{{ $invitation->music['start_at'] }}" data-loop="{{ $invitation->music['loop'] ? '1' : '0' }}">
        <audio preload="none" src="{{ $invitation->music['url'] }}"></audio>
        <button type="button" class="inv-fab inv-music__toggle" data-inv-music-toggle aria-pressed="false"
            aria-label="{{ __('Play music') }}" data-label-play="{{ __('Play music') }}" data-label-pause="{{ __('Pause music') }}">
            <i class="fa-solid fa-music" aria-hidden="true"></i>
        </button>
    </div>
@endif
