<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\InvitationStatus;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'invitation_template_id',
        'title',
        'slug',
        'status',
        'event_type',
        'timezone',
        'published_at',
        'expires_at',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'invitation_template_id',
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

    public function template(): BelongsTo
    {
        // Templates in use are never hard-deleted, but older rows may point at a trashed one.
        return $this->belongsTo(InvitationTemplate::class, 'invitation_template_id')->withTrashed();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(InvitationSection::class)->orderBy('sort_order');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }

    public function guestMessages(): HasMany
    {
        return $this->hasMany(GuestMessage::class);
    }

    public function whatsappCampaigns(): HasMany
    {
        return $this->hasMany(WhatsAppCampaign::class);
    }

    public function guestGroups(): HasMany
    {
        return $this->hasMany(GuestGroup::class)->orderBy('sort_order')->orderBy('name');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(InvitationSetting::class);
    }

    /**
     * @param  Builder<Invitation>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', InvitationStatus::Published)->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === InvitationStatus::Published;
    }

    public function hasExpired(): bool
    {
        return $this->status === InvitationStatus::Expired
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'event_type' => EventType::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
