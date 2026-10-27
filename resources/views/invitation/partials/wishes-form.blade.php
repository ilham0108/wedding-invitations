{{-- Wishes form. Needs $wishes (InvitationRenderer interaction) and $invitation; $formClass styles the form. --}}
<form method="POST" action="{{ $wishes['action'] ?? '#' }}" class="{{ $formClass ?? 'inv-card mx-auto mt-8 max-w-md space-y-4 p-6 text-left' }}"
    @if (! $wishes['action']) x-data x-on:submit.prevent @endif>
    @csrf
    @if ($invitation->interaction['token'] ?? null)
        <input type="hidden" name="token" value="{{ $invitation->interaction['token'] }}">
    @endif
    <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    @unless ($invitation->guestName)
        <label class="block text-sm">
            <span class="font-medium">{{ __('Your name') }}</span>
            <input type="text" name="name" value="{{ old('name') }}" maxlength="80" required class="mt-1 block w-full rounded-md border-black/15 bg-transparent text-sm">
        </label>
    @endunless

    <label class="block text-sm">
        <span class="font-medium">{{ __('Your wishes') }}</span>
        <textarea name="message" rows="3" maxlength="500" required class="mt-1 block w-full rounded-md border-black/15 bg-transparent text-sm">{{ old('message') }}</textarea>
    </label>

    @if ($errors->wishes->any())
        <ul class="space-y-1 text-sm text-red-600" role="alert">
            @foreach ($errors->wishes->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <button type="submit" class="inv-btn w-full" @disabled(! $wishes['action'])>{{ __('Send wishes') }}</button>
</form>
