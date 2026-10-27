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

        <x-ui.card :title="__('Delete account')" :description="__('Your account will be deactivated and you will be signed out.')">
            <x-ui.button variant="danger" type="button" x-data x-on:click="$dispatch('open-modal', 'confirm-user-deletion')">{{ __('Delete account') }}</x-ui.button>
        </x-ui.card>
    </div>

    <x-ui.modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()">
        <form method="POST" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('DELETE')

            <h2 class="text-lg font-semibold text-ink">{{ __('Are you sure you want to delete your account?') }}</h2>
            @if ($user->hasPassword())
                <p class="mt-1 text-sm text-muted">{{ __('Please enter your password to confirm.') }}</p>

                <x-ui.field name="password" for="delete_password" :label="__('Password')" bag="userDeletion" class="mt-5">
                    <x-ui.input name="password" type="password" bag="userDeletion" id="delete_password" />
                </x-ui.field>
            @else
                <p class="mt-1 text-sm text-muted">{{ __('This cannot be undone from your account.') }}</p>
            @endif

            <div class="mt-6 flex justify-end gap-2">
                <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'confirm-user-deletion')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger">{{ __('Delete account') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.app>
