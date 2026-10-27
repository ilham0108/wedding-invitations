<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Notification;

class SubscriptionExpiring extends Notification
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
            'title' => __('Your plan ends soon'),
            'message' => __('The :plan plan ends on :date. Invitations beyond the free plan will go offline unless you renew.', [
                'plan' => $this->subscription->plan->name,
                'date' => $this->subscription->ends_at?->timezone($notifiable->timezone ?? config('app.timezone'))->translatedFormat('d F Y'),
            ]),
            'url' => route('dashboard.subscription.index'),
        ];
    }
}
