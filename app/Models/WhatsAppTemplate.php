<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Client-owned message template with {{variables}} (rules.md section 40).
 */
class WhatsAppTemplate extends Model
{
    use HasUlids;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name',
        'body',
    ];

    protected $hidden = [
        'id',
        'user_id',
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
}
