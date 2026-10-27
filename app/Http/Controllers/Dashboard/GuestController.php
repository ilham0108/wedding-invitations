<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\GuestExport;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\Guest\GuestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestController extends Controller
{
    /**
     * Guest manager for one of the user's invitations (?invitation=ULID, defaults to the latest).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invitation::class);

        $invitations = $request->user()->invitations()->latest('updated_at')->get(['id', 'ulid', 'user_id', 'title']);
        $selected = $invitations->firstWhere('ulid', $request->string('invitation')->toString()) ?? $invitations->first();

        if ($selected !== null) {
            Gate::authorize('update', $selected);
        }

        return view('dashboard.guests.index', [
            'invitations' => $invitations,
            'selected' => $selected,
        ]);
    }

    public function export(Request $request, Invitation $invitation, GuestService $guests): BinaryFileResponse
    {
        Gate::authorize('update', $invitation);

        $format = $request->string('format')->toString() === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;
        $filename = Str::slug($invitation->title).'-guests-'.now()->format('Ymd').'.'.strtolower($format);

        return Excel::download(
            new GuestExport($invitation, $request->only(['search', 'group', 'rsvp', 'status']), $guests),
            $filename,
            $format,
        );
    }

    public function importTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Phone', 'Email', 'Group', 'Guests']);
            fputcsv($output, ['Robert Smith', '081234567890', 'robert@example.com', 'Family', '2']);
            fclose($output);
        }, 'guest-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
