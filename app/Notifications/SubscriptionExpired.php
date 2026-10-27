<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Notification;

class SubscriptionExpired extends Notification
{
    public function __construct(public Subscription $subscription) {}

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
            'title' => __('Your plan has ended'),
            'message' => __('The :plan plan has ended. Invitations beyond the free plan are offline until you renew.', [
                'plan' => $this->subscription->plan->name,
            ]),
            'url' => route('dashboard.subscription.index'),
        ];
    }
}
