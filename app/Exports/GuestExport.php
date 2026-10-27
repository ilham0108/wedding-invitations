<?php

namespace App\Exports;

use App\Models\Guest;
use App\Models\Invitation;
use App\Services\Guest\GuestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Guest export honoring the table filters (rules.md section 35).
 * Every cell is written as text so phone numbers keep their digits.
 */
class GuestExport extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    /**
     * @param  array{search?: ?string, group?: ?string, rsvp?: ?string, status?: ?string}  $filters
     */
    public function __construct(
        private Invitation $invitation,
        private array $filters,
        private GuestService $guests,
    ) {}

    public function query(): Builder|HasMany
    {
        return $this->guests->filteredQuery($this->invitation, $this->filters)->orderBy('name');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Name', 'Phone', 'Email', 'Group', 'Guests', 'Table', 'RSVP', 'Invitation status', 'Personal link', 'Notes'];
    }

    /**
     * @param  Guest  $guest
     * @return array<int, string>
     */
    public function map($guest): array
    {
        return array_map($this->neutralizeFormula(...), [
            $guest->name,
            (string) $guest->phone,
            (string) $guest->email,
            (string) $guest->group?->name,
            (string) $guest->number_of_guests,
            (string) $guest->table_name,
            $guest->rsvp_status->label(),
            $guest->invitation_status->label(),
            $this->guests->personalUrl($guest, $this->invitation),
            (string) $guest->notes,
        ]);
    }

    /**
     * Prevent CSV/formula injection when the file is opened in a spreadsheet app.
     */
    private function neutralizeFormula(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
