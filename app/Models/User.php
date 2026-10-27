<?php

namespace App\Models;

use App\Enums\RoleSlug;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'timezone'])]
#[Hidden(['id', 'password', 'remember_token', 'google_id'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, SoftDeletes;

    /**
     * Mirror the column defaults so freshly created models are complete in memory.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'active',
        'timezone' => 'Asia/Jakarta',
    ];

    /**
     * Columns that receive a generated ULID. The auto-increment `id` stays internal.
     *
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('created_at');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function whatsappTemplates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class)->orderBy('name');
    }

    /**
     * The client's own WhatsApp connection ("My WhatsApp number").
     */
    public function whatsappProvider(): HasOne
    {
        return $this->hasOne(WhatsAppProvider::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function whatsappCampaigns(): HasMany
    {
        return $this->hasMany(WhatsAppCampaign::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeWithRole(Builder $query, RoleSlug $role): void
    {
        $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('slug', $role->value));
    }

    public function hasRole(RoleSlug ...$roles): bool
    {
        $slugs = array_map(fn (RoleSlug $role): string => $role->value, $roles);

        return $this->loadMissing('roles')->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleSlug::SuperAdmin);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->loadMissing('roles.permissions')
            ->roles
            ->pluck('permissions')
            ->flatten()
            ->contains('slug', $permission);
    }

    public function assignRole(RoleSlug $role): void
    {
        $roleModel = Role::query()->where('slug', $role->value)->firstOrFail();

        $this->roles()->syncWithoutDetaching([$roleModel->getKey() => ['created_at' => now()]]);
        $this->unsetRelation('roles');
    }

    /**
     * Accounts created through Google have no password until the owner sets one.
     */
    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
