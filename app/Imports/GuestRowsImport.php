<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the first sheet as arrays keyed by snake_cased headings (e.g. "Nomor HP" → "nomor_hp").
 * Validation and persistence live in GuestImportService, not here.
 */
class GuestRowsImport implements SkipsEmptyRows, ToArray, WithHeadingRow
{
    /**
     * @param  array<int, array<string, mixed>>  $array
     */
    public function array(array $array): void
    {
        // Rows are consumed through Excel::toArray(); nothing to persist here.
    }
}
