<?php

namespace App\Livewire;

use App\Enums\OrderStatus as OrderStatusEnum;
use App\Models\Order;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Order page the customer returns to after paying. It never trusts the redirect itself:
 * while pending it asks the gateway (server to server) for the real status.
 */
class OrderStatus extends Component
{
    private const SYNC_EVERY_SECONDS = 5;

    #[Locked]
    public string $orderUlid = '';

    #[Locked]
    public int $lastSyncedAt = 0;

    public function mount(string $orderUlid): void
    {
        $this->orderUlid = $orderUlid;
        $this->order();
    }

    public function refreshStatus(PaymentService $payments): void
    {
        $order = $this->order();

        if ($order->status !== OrderStatusEnum::Pending || time() - $this->lastSyncedAt < self::SYNC_EVERY_SECONDS) {
            return;
        }

        $this->lastSyncedAt = time();
        $payment = $order->latestPayment()->first();

        if ($payment !== null) {
            $payments->sync($payment);
        }
    }

    public function cancel(PaymentService $payments): void
    {
        $payments->cancel($this->order(), Auth::user());
    }

    public function render(): View
    {
        $order = $this->order()->load('plan:id,name', 'latestPayment', 'subscription:id,ends_at');

        return view('livewire.order-status', [
            'order' => $order,
            'payment' => $order->latestPayment,
            'pending' => $order->status === OrderStatusEnum::Pending,
            'manualInstructions' => $order->latestPayment?->gateway === 'manual' ? config('payment.gateways.manual.instructions') : null,
            'timezone' => Auth::user()->timezone ?? config('app.timezone'),
        ]);
    }

    private function order(): Order
    {
        $order = Order::query()->where('ulid', $this->orderUlid)->firstOrFail();
        abort_unless($order->user_id === Auth::id(), 404);

        return $order;
    }
}
