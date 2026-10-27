<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Services\Payment\PaymentService;
use App\Services\Subscription\SubscriptionLimitService;
use App\Services\WhatsApp\WhatsAppUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function index(Request $request, SubscriptionLimitService $limits, WhatsAppUsageService $whatsappUsage): View
    {
        Gate::authorize('subscription.manage-own');

        $user = $request->user();
        $invitations = $user->invitations()->with('template:id,is_premium')->latest()->get(['id', 'ulid', 'user_id', 'invitation_template_id', 'title', 'created_at']);
        $currentPlan = $limits->plan($user);
        $subscription = $limits->currentSubscription($user);

        $plans = Plan::query()->active()->ordered()->with(['activePrices'])->get()->map(fn (Plan $plan): array => [
            'plan' => $plan,
            'is_current' => $currentPlan?->is($plan) ?? false,
            // Buying a lower plan while a higher one runs is blocked (PaymentService enforces it too).
            'is_lower' => $subscription !== null && $plan->rank < $subscription->plan->rank,
        ]);

        return view('dashboard.subscription.index', [
            'currentPlan' => $currentPlan,
            'subscription' => $subscription,
            'usage' => [
                $this->meter(__('Invitations'), $invitations->count(), $limits->limit($user, 'max_invitations')),
                $this->meter(__('Guests in your largest invitation'), (int) ($user->invitations()->withCount('guests')->get(['id'])->max('guests_count') ?? 0), $limits->limit($user, 'max_guests')),
                $this->meter(__('Platform WhatsApp messages this month'), $whatsappUsage->usedThisMonth($user), $limits->limit($user, 'max_whatsapp_messages')),
            ],
            'offlineInvitations' => $invitations->reject(fn ($invitation): bool => $limits->isInvitationActive($invitation->setRelation('user', $user))),
            'plans' => $plans,
            'orders' => $user->orders()->with('plan:id,name')->latest()->limit(10)->get(),
            'features' => config('plans.features'),
        ]);
    }

    /**
     * @return array{label: string, used: int, limit: ?int, percent: float, level: string}
     */
    private function meter(string $label, int $used, ?int $limit): array
    {
        $ratio = $limit === null ? 0 : ($limit > 0 ? min(1, $used / $limit) : 1);

        return [
            'label' => $label,
            'used' => $used,
            'limit' => $limit,
            'percent' => max(2, round($ratio * 100, 1)),
            'level' => match (true) {
                $ratio >= 1 => 'full',
                $ratio >= 0.8 => 'high',
                default => 'ok',
            },
        ];
    }

    public function checkout(Request $request, PaymentService $payments): RedirectResponse|Response
    {
        Gate::authorize('subscription.manage-own');

        $validated = $request->validate([
            'price' => ['required', 'string', 'size:26'],
            'method' => ['nullable', 'string', 'max:40'],
        ]);
        $price = PlanPrice::query()->where('ulid', $validated['price'])->firstOrFail();

        // Gateways such as Tripay need the channel first: show the picker, then come back here.
        if (empty($validated['method']) && $payments->paymentMethods() !== null) {
            return redirect()->route('dashboard.subscription.method', $price->ulid);
        }

        $order = $payments->checkoutOrFail($request->user(), $price, $validated['method'] ?? null);
        $checkoutUrl = $order->latestPayment()->value('checkout_url');

        return $checkoutUrl
            ? redirect()->away($checkoutUrl)
            : redirect()->route('dashboard.subscription.orders.show', $order);
    }

    /**
     * Payment channel picker for gateways that need it before creating a transaction.
     */
    public function chooseMethod(string $price, PaymentService $payments): View|RedirectResponse
    {
        Gate::authorize('subscription.manage-own');

        $planPrice = PlanPrice::query()->where('ulid', $price)->where('is_active', true)->with('plan:id,name')->firstOrFail();
        $methods = $payments->paymentMethods();

        if ($methods === null) {
            return redirect()->route('dashboard.subscription.index');
        }

        return view('dashboard.subscription.method', ['price' => $planPrice, 'methods' => $methods]);
    }

    public function showOrder(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return view('dashboard.subscription.order', ['order' => $order->load('plan:id,name')]);
    }
}
