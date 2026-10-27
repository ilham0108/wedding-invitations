<x-layouts.auth :title="__('Verify your email')" :show-status="false"
    :subtitle="__('We sent a verification link to your email address. Click it to activate your dashboard.')">
    @if (session('status') === 'verification-link-sent')
        <div class="mb-5 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            {{ __('A new verification link has been sent to your email address.') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button>{{ __('Resend email') }}</x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button variant="ghost">{{ __('Log out') }}</x-ui.button>
        </form>
    </div>
</x-layouts.auth>
