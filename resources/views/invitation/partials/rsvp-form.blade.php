{{-- RSVP form. Needs $rsvp (InvitationRenderer interaction) and $invitation; $formClass styles the form. --}}
@php($attendance = old('attendance', $rsvp['existing']['attendance'] ?? 'attending'))
<form method="POST" action="{{ $rsvp['action'] ?? '#' }}" x-data="{ attendance: @js($attendance) }"
    class="{{ $formClass ?? 'inv-card mx-auto mt-8 max-w-md space-y-4 p-6 text-left' }}"
    @if (! $rsvp['action']) x-on:submit.prevent @endif>
    @csrf
    @if ($invitation->interaction['token'] ?? null)
        <input type="hidden" name="token" value="{{ $invitation->interaction['token'] }}">
    @endif
    <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    @if ($invitation->guestName)
        <p class="text-sm"><span class="inv-muted">{{ __('Responding as') }}</span> <span class="font-semibold">{{ $invitation->guestName }}</span></p>
    @else
        <label class="block text-sm">
            <span class="font-medium">{{ __('Your name') }}</span>
            <input type="text" name="name" value="{{ old('name') }}" maxlength="120" required class="mt-1 block w-full rounded-md border-black/15 bg-transparent text-sm">
        </label>
    @endif

    <fieldset class="space-y-2">
        <legend class="text-sm font-medium">{{ __('Will you attend?') }}</legend>
        <div class="grid grid-cols-2 gap-2">
            @foreach (['attending' => __('Yes, I will attend'), 'not_attending' => __('Sorry, I can\'t')] as $value => $label)
                <label class="flex cursor-pointer items-center gap-2 rounded-md border border-black/15 px-3 py-2 text-sm" x-bind:class="attendance === '{{ $value }}' && 'ring-2 ring-[var(--inv-primary)]'">
                    <input type="radio" name="attendance" value="{{ $value }}" x-model="attendance" @checked($attendance === $value)>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <div x-show="attendance === 'attending'" class="space-y-4">
        <label class="block text-sm">
            <span class="font-medium">{{ __('Number of people') }}</span>
            <select name="number_of_guests" class="mt-1 block w-full rounded-md border-black/15 bg-transparent text-sm">
                @foreach (range(1, $rsvp['max_guests']) as $count)
                    <option value="{{ $count }}" @selected((int) old('number_of_guests', $rsvp['existing']['number_of_guests'] ?? 1) === $count)>{{ $count }}</option>
                @endforeach
            </select>
        </label>

        @if ($rsvp['meal_options'])
            <label class="block text-sm">
                <span class="font-medium">{{ __('Meal preference') }}</span>
                <select name="meal_preference" class="mt-1 block w-full rounded-md border-black/15 bg-transparent text-sm">
                    <option value="">{{ __('No preference') }}</option>
                    @foreach ($rsvp['meal_options'] as $option)
                        <option value="{{ $option }}" @selected(old('meal_preference', $rsvp['existing']['meal_preference'] ?? null) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    <label class="block text-sm">
        <span class="font-medium">{{ __('Message (optional)') }}</span>
        <textarea name="message" rows="3" maxlength="500" class="mt-1 block w-full rounded-md border-black/15 bg-transparent text-sm">{{ old('message', $rsvp['existing']['message'] ?? '') }}</textarea>
    </label>

    @if ($errors->rsvp->any())
        <ul class="space-y-1 text-sm text-red-600" role="alert">
            @foreach ($errors->rsvp->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <button type="submit" class="inv-btn w-full" @disabled(! $rsvp['action'])>{{ __('Send RSVP') }}</button>
    @if (! $rsvp['action'])
        <p class="text-center text-xs inv-muted">{{ __('Preview mode: responses are not saved.') }}</p>
    @endif
</form>
