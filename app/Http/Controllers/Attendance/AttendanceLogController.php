<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceLogStatus;
use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceLogIndexRequest;
use App\Models\AttendanceLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The raw punches as received, including the flagged ones (unknown IDs,
 * future times) that HR needs to resolve.
 */
class AttendanceLogController extends Controller
{
    public function index(AttendanceLogIndexRequest $request): View
    {
        $filters = $request->validated();

        $logs = $this->filtered($filters)
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
    public function destroy(AttendanceLogIndexRequest $request, AuditLogger $audit): RedirectResponse
    {
        $filters = $request->validated();

        $query = $this->filtered($filters);
        $count = (clone $query)->count();
        $oldest = (clone $query)->min('logged_at');
        $newest = (clone $query)->max('logged_at');

        $query->delete();

        $audit->record(
            AuditEvent::Deleted,
            __('Cleared :count attendance logs', ['count' => $count]),
            oldValues: [
                'filters' => $filters === [] ? __('none (all logs)') : $filters,
                'count' => $count,
                'earliest_punch' => $oldest,
                'latest_punch' => $newest,
            ],
        );

        return redirect()
            ->route('attendance.logs')
            ->with('status', trans_choice(':count attendance log cleared.|:count attendance logs cleared.', $count, ['count' => $count]));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceLog>
     */
    private function filtered(array $filters): Builder
    {
        return AttendanceLog::query()
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereBetween('logged_at', [$date.' 00:00:00', $date.' 23:59:59']))
            ->when($filters['biometric_user_id'] ?? null, fn (Builder $query, string $id) => $query->where('biometric_user_id', $id));
    }
}
