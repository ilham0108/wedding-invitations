<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A design imported from an HTML package (HtmlDesignImporter). "sections" holds the checked markup as plain data.
 */
class UploadedDesign extends Model
{
    use HasUlids;

    protected $fillable = ['key', 'name', 'directory', 'sections', 'created_by'];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sections' => 'array'];
    }
}
