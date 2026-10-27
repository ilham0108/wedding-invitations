<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasUlids;

    protected $fillable = [
        'status',
        'billing_period',
        'starts_at',
        'ends_at',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'plan_id',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * Feature values frozen at purchase time.
     *
     * @return array<string, int|bool|null>
     */
    public function features(): array
    {
        return $this->items->mapWithKeys(fn (SubscriptionItem $item): array => [$item->feature => $item->typedValue()])->all();
    }

    /**
     * Same rule as scopeCurrent(), for an already loaded subscription.
     */
    public function isCurrent(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && $this->starts_at <= now()
            && ($this->ends_at === null || $this->ends_at > now());
    }

    /**
     * @param  Builder<Subscription>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Active)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_period' => BillingPeriod::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }
}
