<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use Database\Factories\PlanPriceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'billing_period',
        'duration_days',
        'amount',
        'currency',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'plan_id',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Human label such as "Rp 149.000 · 365 days" or "Rp 299.000 / month".
     */
    public function periodLabel(): string
    {
        return match ($this->billing_period) {
            BillingPeriod::OneTime => trans_choice(':count day access|:count days access', (int) $this->duration_days),
            BillingPeriod::Monthly => __('per month'),
            BillingPeriod::Yearly => __('per year'),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class,
            'duration_days' => 'integer',
            'amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
