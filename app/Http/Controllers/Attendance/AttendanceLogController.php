<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceLogStatus;
use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceLogIndexRequest;
use App\Models\AttendanceLog;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The raw punches as received, including the flagged ones (unknown IDs,
 * future times) that HR needs to resolve.
 */
class AttendanceLogController extends Controller
{
    use SendsChangesForApproval;

    public function index(AttendanceLogIndexRequest $request): View
    {
        $filters = $request->validated();

        $logs = AttendanceLog::query()->matchingFilters($filters)
            ->with(['employee', 'device'])
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('attendance.logs', [
            'logs' => $logs,
            'filters' => $filters,
            'statuses' => AttendanceLogStatus::options(),
            'flaggedCount' => AttendanceLog::query()->where('status', '!=', AttendanceLogStatus::Valid)->count(),
        ]);
    }

    /**
     * Clear the punches matching the current filters (every punch when none
     * are set). The DTRs already built from them are left as they are, and
     * one audit entry records who cleared what.
     */
    public function destroy(AttendanceLogIndexRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::ClearAttendanceLogs, ['filters' => $request->validated()]);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('attendance.logs'));
        }

        $count = $outcome->result;

        return redirect()
            ->route('attendance.logs')
            ->with('status', trans_choice(':count attendance log cleared.|:count attendance logs cleared.', $count, ['count' => $count]));
    }
}
