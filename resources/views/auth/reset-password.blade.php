<x-layouts.auth :title="__('Choose a new password')">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.field name="email" :label="__('Email')">
            <x-ui.input name="email" type="email" :value="$request->email" required autofocus autocomplete="username" />
        </x-ui.field>

        <x-ui.field name="password" :label="__('New password')">
            <x-ui.input name="password" type="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field name="password_confirmation" :label="__('Confirm password')">
            <x-ui.input name="password_confirmation" type="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button class="w-full">{{ __('Reset password') }}</x-ui.button>
    </form>
</x-layouts.auth>
