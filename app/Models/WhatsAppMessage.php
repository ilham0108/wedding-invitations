<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of a single provider API call. Never stores credentials.
 */
class WhatsAppMessage extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * API call logs are kept this long, then removed by the scheduled model:prune.
     */
    public const PRUNE_AFTER_DAYS = 90;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'provider',
        'attempt',
        'success',
        'http_status',
        'provider_message_id',
        'error',
        'response',
        'duration_ms',
    ];

    protected $hidden = [
        'id',
        'whatsapp_campaign_recipient_id',
    ];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignRecipient::class, 'whatsapp_campaign_recipient_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'response' => 'array',
        ];
    }
}
