<x-layouts.app area="dashboard" :title="__('Settings')">
    <div class="max-w-2xl space-y-6">
        <x-ui.card :title="__('Profile')" :description="__('Your name, contact details, and timezone.')">
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
                @csrf
                @method('PATCH')

                <x-ui.field name="name" :label="__('Full name')">
                    <x-ui.input name="name" :value="$user->name" required autocomplete="name" />
                </x-ui.field>

                <x-ui.field name="email" :label="__('Email')">
                    <x-ui.input name="email" type="email" :value="$user->email" required autocomplete="username" />
                </x-ui.field>

                <x-ui.field name="phone" :label="__('Phone (optional)')" :hint="__('Used for WhatsApp notifications later.')">
                    <x-ui.input name="phone" type="tel" :value="$user->phone" placeholder="+62 812 3456 7890" autocomplete="tel" />
                </x-ui.field>

                <x-ui.field name="timezone" :label="__('Timezone')">
                    <x-ui.select name="timezone" :value="$user->timezone" :options="array_combine($timezones, $timezones)" />
                </x-ui.field>

                <div class="flex items-center gap-3">
                    <x-ui.button>{{ __('Save changes') }}</x-ui.button>
                    @if (session('status') === 'profile-updated')
                        <p class="text-sm text-emerald-700">{{ __('Saved.') }}</p>
                    @endif
                </div>
            </form>
        </x-ui.card>

        <x-ui.card
            :title="$user->hasPassword() ? __('Change password') : __('Set a password')"
            :description="$user->hasPassword() ? __('Use a long, unique password to keep your account secure.') : __('You sign in with Google. Set a password to also log in with your email.')">
            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                @if ($user->hasPassword())
                    <x-ui.field name="current_password" :label="__('Current password')" bag="updatePassword">
                        <x-ui.input name="current_password" type="password" bag="updatePassword" autocomplete="current-password" />
                    </x-ui.field>
                @endif

                <x-ui.field name="password" for="new_password" :label="__('New password')" bag="updatePassword">
                    <x-ui.input name="password" type="password" bag="updatePassword" id="new_password" autocomplete="new-password" />
                </x-ui.field>

                <x-ui.field name="password_confirmation" :label="__('Confirm new password')" bag="updatePassword">
                    <x-ui.input name="password_confirmation" type="password" bag="updatePassword" autocomplete="new-password" />
                </x-ui.field>

                <div class="flex items-center gap-3">
                    <x-ui.button>{{ $user->hasPassword() ? __('Update password') : __('Set password') }}</x-ui.button>
                    @if (session('status') === 'password-updated')
                        <p class="text-sm text-emerald-700">{{ __('Password updated.') }}</p>
                    @endif
                </div>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('Delete account')" :description="__('Removes your account, invitations, and guest data for good.')">
            <x-ui.button variant="danger" type="button" x-data x-on:click="$dispatch('open-modal', 'confirm-user-deletion')">{{ __('Delete account') }}</x-ui.button>
        </x-ui.card>
    </div>

    <x-ui.modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()">
        <form method="POST" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('DELETE')

            <h2 class="text-lg font-semibold text-ink">{{ __('Delete your account permanently?') }}</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ __('This cannot be undone. The following is deleted right away:') }}</p>

            {{-- Real counts from AccountDeletionService::summary(), so the user sees what is lost. --}}
            <ul class="mt-3 list-disc space-y-1 rounded-lg border border-red-200 bg-red-50 py-3 pl-8 pr-4 text-sm text-red-900">
                <li>{{ trans_choice(':count invitation, with its sections, photos, and music|:count invitations, with their sections, photos, and music', $deletion['invitations'], ['count' => Number::format($deletion['invitations'])]) }}</li>
                <li>{{ trans_choice(':count guest, with their personal link, RSVP, and wishes|:count guests, with their personal links, RSVPs, and wishes', $deletion['guests'], ['count' => Number::format($deletion['guests'])]) }}</li>
                <li>{{ __('Your WhatsApp campaigns, message templates, and connected WhatsApp number') }}</li>
            </ul>

            <p class="mt-3 text-sm text-ink-soft">
                {{ __('Invitation links you have shared stop working, and an active plan ends. Payment records are kept without your name or email.') }}
                @if ($deletion['guests'] > 0)
                    <a href="{{ route('dashboard.guests.index') }}" class="font-medium text-sage underline underline-offset-2 hover:text-ink">{{ __('Export your guest list first') }}</a>
                    {{ __('if you still need it.') }}
                @endif
            </p>

            @if ($user->hasPassword())
                <x-ui.field name="password" for="delete_password" :label="__('Password')" bag="userDeletion" class="mt-5">
                    <x-ui.input name="password" type="password" bag="userDeletion" id="delete_password" autocomplete="current-password" />
                </x-ui.field>
            @endif

            <div class="mt-5">
                <label for="confirm_permanent" class="flex cursor-pointer items-start gap-3 text-sm text-ink">
                    <input id="confirm_permanent" type="checkbox" name="confirm_permanent" value="1" required
                        class="mt-0.5 size-4 cursor-pointer rounded border-line text-red-600 focus:ring-red-600">
                    <span>{{ __('I understand that my invitations and guest data are deleted permanently and cannot be recovered.') }}</span>
                </label>
                @error('confirm_permanent', 'userDeletion')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- On phones "Keep my account" sits on top, so a stray tap lands on the safe choice; 44px targets. --}}
            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="secondary" type="button" class="h-11 sm:h-10" x-on:click="$dispatch('close-modal', 'confirm-user-deletion')">{{ __('Keep my account') }}</x-ui.button>
                <x-ui.button variant="danger" class="h-11 sm:h-10">{{ __('Delete permanently') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.app>
