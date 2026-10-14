<?php

namespace App\Http\Controllers\Reports;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The reports the signed-in user may run: every office for the HR Office,
 * their own office for department heads.
 */
class ReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $seesAll = $user->can(Permission::GenerateReports);

        $reports = collect([
            [
                'title' => __('Employee List'),
                'description' => $seesAll ? __('Employees by office, with position, employment type, salary grade and date hired.') : __('Employees of your office.'),
                'route' => route('reports.employees'),
                'icon' => 'users',
                'visible' => true,
            ],
            [
                'title' => __('Attendance Summary'),
                'description' => __('Monthly working days, absences, tardiness, undertime and missing punches per employee.'),
                'route' => route('attendance.summary'),
                'icon' => 'clock',
                'visible' => $user->can(Permission::ManageAttendance) || $user->can(Permission::ViewDepartmentAttendance),
            ],
            [
                'title' => __('Leave Utilization'),
                'description' => __('Approved leave taken in a month or year, by leave type and office.'),
                'route' => route('leave.reports.utilization'),
                'icon' => 'calendar',
                'visible' => true,
            ],
            [
                'title' => __('Payroll Summary'),
                'description' => __('Released payrolls by office and by pay period, with contributions, tax and employer shares.'),
                'route' => route('reports.payroll'),
                'icon' => 'banknotes',
                'visible' => $user->can(Permission::ProcessPayroll),
            ],
        ])->where('visible', true)->values();

        return view('reports.index', ['reports' => $reports, 'seesAll' => $seesAll]);
    }
}
