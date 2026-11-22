<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\ChangeAction;
use App\Enums\Permission;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ImportAttendanceRequest;
use App\Services\Approvals\ChangeRequestService;
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
    use SendsChangesForApproval;

    public function create(): View
    {
        Gate::authorize(Permission::ManageAttendance);

        return view('attendance.import', [
            'columns' => AttendanceCsvImporter::COLUMNS,
            'maxRows' => AttendanceCsvImporter::MAX_ROWS,
        ]);
    }

    public function store(ImportAttendanceRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform(
            $request->user(),
            ChangeAction::ImportAttendance,
            ['filename' => $request->file('file')->getClientOriginalName()],
            files: ['file' => $request->file('file')],
        );

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('attendance.import.create'));
        }

        $result = $outcome->result;

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
