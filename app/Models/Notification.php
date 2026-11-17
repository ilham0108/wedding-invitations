<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The framework's notifications table, here only so model:prune can clear old read notifications.
 * Code that reads notifications keeps using $user->notifications().
 */
class Notification extends DatabaseNotification
{
    use MassPrunable;

    /**
     * Read notifications are kept this long; unread ones stay until they are read.
     */
    public const PRUNE_AFTER_DAYS = 90;

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->whereNotNull('read_at')->where('read_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
