<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

class PaymentFailed extends Notification
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
            'title' => __('Payment not completed'),
            'message' => __('Order :number for the :plan plan was not paid. You can start a new checkout anytime.', [
                'number' => $this->order->number,
                'plan' => $this->order->plan->name,
            ]),
            'url' => route('dashboard.subscription.index'),
        ];
    }
}
