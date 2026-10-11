<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceLogStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceLogIndexRequest;
use App\Models\AttendanceLog;
use Illuminate\Database\Eloquent\Builder;
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

        $logs = AttendanceLog::query()
            ->with(['employee', 'device'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn (Builder $query, string $date) => $query->whereBetween('logged_at', [$date.' 00:00:00', $date.' 23:59:59']))
            ->when($filters['biometric_user_id'] ?? null, fn (Builder $query, string $id) => $query->where('biometric_user_id', $id))
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
}
