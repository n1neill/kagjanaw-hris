<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\MonthRequest;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceReportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The signed-in employee's own Daily Time Record for a month.
 */
class MyAttendanceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(MonthRequest $request, AttendanceReportService $reports): View
    {
        $employee = $request->user()->employee;

        if ($employee === null) {
            return view('employees.no-record');
        }

        Gate::authorize('viewAttendance', $employee);

        $month = $request->month();

        return view('attendance.my', [
            'employee' => $employee,
            'month' => $month,
            'schedule' => WorkSchedule::current(),
            ...$reports->monthlyRecord($employee, $month),
        ]);
    }
}
