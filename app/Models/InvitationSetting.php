<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationSetting extends Model
{
    protected $fillable = [
        'design',
        'template_theme',
        'theme',
        'decoration',
        'music',
        'story',
        'meta_title',
        'meta_description',
        'og_image',
        'allow_search_indexing',
        'guestbook_auto_approve',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_theme' => 'array',
            'theme' => 'array',
            'decoration' => 'array',
            'music' => 'array',
            'story' => 'array',
            'allow_search_indexing' => 'boolean',
            'guestbook_auto_approve' => 'boolean',
        ];
    }
}
