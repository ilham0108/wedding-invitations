<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of a single provider API call. Never stores credentials.
 */
class WhatsAppMessage extends Model
{
    public const UPDATED_AT = null;

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
