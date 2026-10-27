<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One section of the marketing site (config/site-content.php), edited in the admin CMS.
 */
class SiteContent extends Model
{
    protected $fillable = [
        'section',
        'content',
    ];

    protected $hidden = [
        'id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
