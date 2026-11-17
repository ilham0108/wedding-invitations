<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * Entries are kept this long, then removed by the scheduled model:prune.
     */
    public const PRUNE_AFTER_DAYS = 365;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
