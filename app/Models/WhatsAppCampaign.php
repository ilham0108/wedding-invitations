<?php

namespace App\Models;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppSender;
use Database\Factories\WhatsAppCampaignFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCampaign extends Model
{
    /** @use HasFactory<WhatsAppCampaignFactory> */
    use HasFactory, HasUlids;

    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'name',
        'message',
        'status',
        'sender',
        'timezone',
        'scheduled_at',
    ];

    protected $attributes = [
        'sender' => 'platform',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'invitation_id',
        'whatsapp_template_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'whatsapp_campaign_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WhatsAppCampaignStatus::class,
            'sender' => WhatsAppSender::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_recipients' => 'integer',
        ];
    }
}
