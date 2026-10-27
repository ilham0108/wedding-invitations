<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\Notifies;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin: orders and payment attempts. Manual transfers are confirmed here.
 */
class OrderManager extends Component
{
    use Notifies, WithPagination;

    #[Locked]
    public string $tab = 'orders';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $search = '';

    public function mount(string $tab = 'orders'): void
    {
        $this->tab = $tab === 'payments' ? 'payments' : 'orders';
        Gate::authorize($this->tab === 'payments' ? 'payments.manage' : 'orders.manage');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function confirmPayment(string $orderUlid, PaymentService $payments): void
    {
        Gate::authorize('payments.manage');

        $order = Order::query()->where('ulid', $orderUlid)->where('status', OrderStatus::Pending)->firstOrFail();
        $payments->confirmManually($order, Auth::user());

        $this->notify('success', __('Payment confirmed and plan activated for order :number.', ['number' => $order->number]));
    }

    public function cancelOrder(string $orderUlid, PaymentService $payments): void
    {
        Gate::authorize('orders.manage');

        $order = Order::query()->where('ulid', $orderUlid)->firstOrFail();
        $payments->cancel($order, Auth::user());

        $this->notify('success', __('Order :number cancelled.', ['number' => $order->number]));
    }

    public function render(): View
    {
        $search = trim($this->search);
        $like = '%'.addcslashes($search, '%_\\').'%';

        if ($this->tab === 'payments') {
            $status = PaymentStatus::tryFrom($this->status);
            $rows = Payment::query()
                ->with('order:id,ulid,number,user_id,plan_id', 'order.user:id,name,email', 'order.plan:id,name')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where(fn (Builder $inner) => $inner
                    ->where('external_id', 'like', $like)
                    ->orWhere('gateway_reference', 'like', $like)))
                ->latest()
                ->paginate(20);
            $statuses = PaymentStatus::cases();
        } else {
            $status = OrderStatus::tryFrom($this->status);
            $rows = Order::query()
                ->with('user:id,name,email', 'plan:id,name', 'latestPayment')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where(fn (Builder $inner) => $inner
                    ->where('number', 'like', $like)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', $like)->orWhere('name', 'like', $like))))
                ->latest()
                ->paginate(20);
            $statuses = OrderStatus::cases();
        }

        return view('livewire.order-manager', [
            'rows' => $rows,
            'statuses' => $statuses,
            'revenueThisMonth' => (int) Order::query()->where('status', OrderStatus::Paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'pendingCount' => Order::query()->where('status', OrderStatus::Pending)->count(),
        ]);
    }
}
