<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionItem extends Model
{
    protected $fillable = [
        'feature',
        'value',
    ];

    protected $hidden = [
        'id',
        'subscription_id',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function typedValue(): int|bool|null
    {
        $value = json_decode((string) $this->value, true);

        return is_int($value) || is_bool($value) ? $value : null;
    }
}
