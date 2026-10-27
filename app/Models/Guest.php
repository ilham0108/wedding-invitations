<?php

namespace App\Models;

use App\Enums\GuestInvitationStatus;
use App\Enums\RsvpStatus;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public const TOKEN_LENGTH = 32;

    protected $fillable = [
        'guest_group_id',
        'name',
        'phone',
        'email',
        'number_of_guests',
        'table_name',
        'rsvp_status',
        'invitation_status',
        'notes',
    ];

    protected $hidden = [
        'id',
        'invitation_id',
        'guest_group_id',
        'token',
    ];

    protected $attributes = [
        'number_of_guests' => 1,
        'rsvp_status' => 'pending',
        'invitation_status' => 'not_sent',
    ];

    protected static function booted(): void
    {
        static::creating(function (Guest $guest): void {
            $guest->token ??= static::generateToken();
        });
    }

    /**
     * Unguessable token for the personal link; never derived from the id.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (static::withTrashed()->where('token', $token)->exists());

        return $token;
    }

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

    public function rsvp(): HasOne
    {
        return $this->hasOne(Rsvp::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GuestGroup::class, 'guest_group_id');
    }

    /**
     * @param  Builder<Guest>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $term);

        $query->where(function (Builder $inner) use ($term, $digits): void {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $inner->where('name', 'like', $like)->orWhere('email', 'like', $like);

            if (strlen($digits) >= 3) {
                $inner->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number_of_guests' => 'integer',
            'rsvp_status' => RsvpStatus::class,
            'invitation_status' => GuestInvitationStatus::class,
            'opened_at' => 'datetime',
        ];
    }
}
