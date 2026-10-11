<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\MonthRequest;
use App\Models\Employee;
use App\Services\Attendance\DtrCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Month-level HR actions on a Daily Time Record.
 */
class DtrMonthController extends Controller
{
    /**
     * Confirm the month's finished days as reviewed (required before payroll).
     */
    public function validateMonth(MonthRequest $request, Employee $employee, DtrCorrectionService $corrections): RedirectResponse
    {
        Gate::authorize('manageAttendance', $employee);

        $month = $request->month();
        $count = $corrections->validateMonth($employee, $month, $request->user());

        return redirect()
            ->route('attendance.dtr.show', [$employee, 'month' => $month->format('Y-m')])
            ->with('status', trans_choice('{0} All days of :month were already validated.|{1} :count day of :month validated.|[2,*] :count days of :month validated.', $count, [
                'count' => $count,
                'month' => $month->format('F Y'),
            ]));
    }

    /**
     * Recompute the month from raw logs; corrected days are kept.
     */
    public function reprocess(MonthRequest $request, Employee $employee, DtrCorrectionService $corrections): RedirectResponse
    {
        Gate::authorize('manageAttendance', $employee);

        $month = $request->month();
        $corrections->reprocessMonth($employee, $month);

        return redirect()
            ->route('attendance.dtr.show', [$employee, 'month' => $month->format('Y-m')])
            ->with('status', __('The DTR for :month was recomputed from the attendance logs.', ['month' => $month->format('F Y')]));
    }
}
