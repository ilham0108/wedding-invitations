<?php

namespace App\Models;

use App\Enums\RsvpAttendance;
use Database\Factories\RsvpFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rsvp extends Model
{
    /** @use HasFactory<RsvpFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'attendance',
        'number_of_guests',
        'meal_preference',
        'message',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance' => RsvpAttendance::class,
            'number_of_guests' => 'integer',
        ];
    }
}
