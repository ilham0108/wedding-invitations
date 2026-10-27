<x-layouts.auth :title="__('Welcome back')" :subtitle="__('Log in to manage your invitations.')">
    @if ($googleEnabled)
        <x-auth.google-button :label="__('Continue with Google')" />
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-ui.field name="email" :label="__('Email')">
            <x-ui.input name="email" type="email" required autofocus autocomplete="username" />
        </x-ui.field>

        <x-ui.field name="password" :label="__('Password')">
            <x-ui.input name="password" type="password" required autocomplete="current-password" />
        </x-ui.field>

        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center gap-2 text-sm text-ink-soft">
                <input id="remember" type="checkbox" name="remember" class="rounded border-line text-sage focus:ring-sage">
                {{ __('Remember me') }}
            </label>

            <a href="{{ route('password.request') }}" class="text-sm font-medium text-sage hover:text-ink">{{ __('Forgot password?') }}</a>
        </div>

        <x-ui.button class="w-full">{{ __('Log in') }}</x-ui.button>
    </form>

    <x-slot:footer>
        <p class="mt-6 text-sm text-muted">
            {{ __("Don't have an account?") }}
            <a href="{{ route('register') }}" class="font-medium text-ink hover:underline">{{ __('Create one') }}</a>
        </p>
    </x-slot:footer>
</x-layouts.auth>
