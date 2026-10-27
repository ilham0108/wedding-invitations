<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class PaymentSucceeded extends Notification
{
    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Payment received'),
            'message' => __('Your :plan plan is active until :date.', [
                'plan' => $this->order->plan->name,
                'date' => $this->order->subscription?->ends_at?->timezone($notifiable->timezone ?? config('app.timezone'))->translatedFormat('d F Y') ?? '—',
            ]),
            'url' => route('dashboard.subscription.index'),
        ];
    }
}
