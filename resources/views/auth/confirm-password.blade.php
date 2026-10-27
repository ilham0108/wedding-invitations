<x-layouts.auth :title="__('Confirm your password')" :subtitle="__('This is a secure area. Please confirm your password to continue.')">
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <x-ui.field name="password" :label="__('Password')">
            <x-ui.input name="password" type="password" required autocomplete="current-password" />
        </x-ui.field>

        <x-ui.button class="w-full">{{ __('Confirm') }}</x-ui.button>
    </form>
</x-layouts.auth>
