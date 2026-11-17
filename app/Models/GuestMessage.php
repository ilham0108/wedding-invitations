<?php

namespace App\Models;

use App\Enums\GuestMessageStatus;
use Database\Factories\GuestMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuestMessage extends Model
{
    /** @use HasFactory<GuestMessageFactory> */
    use HasFactory, HasUlids, MassPrunable, SoftDeletes;

    /**
     * Deleted wishes are removed for good after this many days (scheduled model:prune).
     */
    public const PRUNE_AFTER_DAYS = 30;

    protected $fillable = [
        'name',
        'message',
        'status',
    ];

    protected $hidden = [
        'id',
        'invitation_id',
        'guest_id',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * @param  Builder<GuestMessage>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', GuestMessageStatus::Approved);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GuestMessageStatus::class,
        ];
    }
}
