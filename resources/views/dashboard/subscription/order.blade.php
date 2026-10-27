<x-layouts.app area="dashboard" :title="__('Order :number', ['number' => $order->number])">
    <x-slot:actions>
        <x-ui.button :href="route('dashboard.subscription.index')" variant="secondary" icon="fa-solid fa-arrow-left">{{ __('Back to plans') }}</x-ui.button>
    </x-slot:actions>

    <livewire:order-status :order-ulid="$order->ulid" />
</x-layouts.app>
