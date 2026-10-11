<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\MonthRequest;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * An employee's Daily Time Record for a month, on screen and as
 * Civil Service Form No. 48 (PDF).
 */
class DtrController extends Controller
{
    public function show(MonthRequest $request, Employee $employee, AttendanceReportService $reports): View
    {
        Gate::authorize('viewAttendance', $employee);

        $month = $request->month();
        $employee->load(['department', 'position']);

        return view('attendance.dtr', [
            'employee' => $employee,
            'month' => $month,
            'schedule' => WorkSchedule::current(),
            'canManage' => $request->user()->can('manageAttendance', $employee),
            'isValidated' => $employee->dailyTimeRecords()
                ->whereBetween('work_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
                ->whereNotNull('validated_at')
                ->exists(),
            ...$reports->monthlyRecord($employee, $month),
        ]);
    }

    public function pdf(MonthRequest $request, Employee $employee, AttendanceReportService $reports): Response
    {
        Gate::authorize('viewAttendance', $employee);

        $month = $request->month();

        return Pdf::loadView('attendance.dtr-pdf', [
            'employee' => $employee,
            'month' => $month,
            'schedule' => WorkSchedule::current(),
            ...$reports->monthlyRecord($employee, $month),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true)
            ->stream("DTR-{$employee->employee_number}-{$month->format('Y-m')}.pdf");
    }
}
