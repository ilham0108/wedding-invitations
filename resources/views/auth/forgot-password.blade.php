<x-layouts.auth :title="__('Forgot your password?')" :subtitle="__('Enter your email and we will send you a link to choose a new password.')">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-ui.field name="email" :label="__('Email')">
            <x-ui.input name="email" type="email" required autofocus />
        </x-ui.field>

        <x-ui.button class="w-full">{{ __('Email reset link') }}</x-ui.button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="mt-6 text-sm font-medium text-ink hover:underline">{{ __('Back to log in') }}</a>
    </x-slot:footer>
</x-layouts.auth>
