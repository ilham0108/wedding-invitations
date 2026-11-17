<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A row of the database cache store. The store only drops an expired row when that key is read
 * again, so keys nobody reads anymore (preview wishes, builder drafts, import previews) stay
 * forever. model:prune removes them.
 */
class CacheEntry extends Model
{
    use MassPrunable;

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public function getTable(): string
    {
        return (string) config('cache.stores.database.table', 'cache');
    }

    public function getConnectionName(): ?string
    {
        return config('cache.stores.database.connection') ?: parent::getConnectionName();
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expiration', '<=', now()->getTimestamp());
    }
}
