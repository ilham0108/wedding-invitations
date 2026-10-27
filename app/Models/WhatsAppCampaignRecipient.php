<?php

namespace App\Models;

use App\Enums\WhatsAppRecipientStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCampaignRecipient extends Model
{
    use HasUlids;

    protected $table = 'whatsapp_campaign_recipients';

    protected $fillable = [
        'name',
        'phone',
        'message',
        'status',
    ];

    protected $hidden = [
        'id',
        'whatsapp_campaign_id',
        'guest_id',
        'whatsapp_provider_id',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'whatsapp_campaign_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function attemptsLog(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_campaign_recipient_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WhatsAppRecipientStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
