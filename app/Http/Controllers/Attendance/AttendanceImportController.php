<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ImportAttendanceRequest;
use App\Services\Attendance\AttendanceCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload attendance punches from a CSV file.
 */
class AttendanceImportController extends Controller
{
    public function create(): View
    {
        Gate::authorize(Permission::ManageAttendance);

        return view('attendance.import', [
            'columns' => AttendanceCsvImporter::COLUMNS,
            'maxRows' => AttendanceCsvImporter::MAX_ROWS,
        ]);
    }

    public function store(ImportAttendanceRequest $request, AttendanceCsvImporter $importer): RedirectResponse
    {
        $result = $importer->import($request->file('file')->getRealPath());

        return redirect()
            ->route('attendance.import.create')
            ->with('importResult', [...$result, 'filename' => $request->file('file')->getClientOriginalName()]);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize(Permission::ManageAttendance);

        return response()->streamDownload(
            function (): void {
                echo AttendanceCsvImporter::template();
            },
            'attendance-import-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
