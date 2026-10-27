<x-layouts.auth :title="__('Create your account')" :subtitle="__('Start building beautiful digital invitations.')">
    @unless ($registrationOpen)
        <div class="rounded-lg bg-paper p-4 text-sm text-ink-soft" role="status">
            <p class="font-medium text-ink">{{ __('Registration is currently closed.') }}</p>
            <p class="mt-1">{{ __('New accounts cannot be created at the moment. If you already have an account, you can log in.') }}</p>
        </div>
    @else
    @if ($googleEnabled)
        <x-auth.google-button :label="__('Sign up with Google')" />
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <x-ui.field name="name" :label="__('Full name')">
            <x-ui.input name="name" required autofocus autocomplete="name" />
        </x-ui.field>

        <x-ui.field name="email" :label="__('Email')">
            <x-ui.input name="email" type="email" required autocomplete="username" />
        </x-ui.field>

        <x-ui.field name="password" :label="__('Password')" :hint="__('At least 8 characters.')">
            <x-ui.input name="password" type="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field name="password_confirmation" :label="__('Confirm password')">
            <x-ui.input name="password_confirmation" type="password" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button class="w-full">{{ __('Create account') }}</x-ui.button>
    </form>
    @endunless

    <x-slot:footer>
        <p class="mt-6 text-sm text-muted">
            {{ __('Already registered?') }}
            <a href="{{ route('login') }}" class="font-medium text-ink hover:underline">{{ __('Log in') }}</a>
        </p>
    </x-slot:footer>
</x-layouts.auth>
