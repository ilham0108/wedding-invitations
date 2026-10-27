<?php

namespace App\Models;

use App\Enums\WhatsAppDriver;
use Database\Factories\WhatsAppProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-configured WhatsApp provider. Credentials are encrypted at rest and hidden from serialization.
 */
class WhatsAppProvider extends Model
{
    /** @use HasFactory<WhatsAppProviderFactory> */
    use HasFactory, HasUlids;

    protected $table = 'whatsapp_providers';

    protected $fillable = [
        'driver',
        'name',
        'credentials',
        'rate_per_minute',
        'is_active',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'credentials',
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

    public function credential(string $key, ?string $default = null): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Owner of a client connection; null for platform providers managed by the admin.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPlatform(): bool
    {
        return $this->user_id === null;
    }

    /**
     * @param  Builder<WhatsAppProvider>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<WhatsAppProvider>  $query
     */
    public function scopePlatform(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => WhatsAppDriver::class,
            'credentials' => 'encrypted:array',
            'rate_per_minute' => 'integer',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
