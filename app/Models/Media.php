<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'invitation_id',
        'collection',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
        'sort_order',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'invitation_id',
        'disk',
        'path',
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

    /**
     * Root-relative public URL, so stored references survive APP_URL changes.
     */
    public function url(): string
    {
        return parse_url(Storage::disk($this->disk)->url($this->path), PHP_URL_PATH);
    }
}
