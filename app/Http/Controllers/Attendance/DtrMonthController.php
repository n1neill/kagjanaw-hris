<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\MonthRequest;
use App\Models\Employee;
use App\Services\Attendance\DtrCorrectionService;
use App\Services\Leave\LeaveCreditPostingService;
use App\Services\Leave\LeaveDays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Month-level HR actions on a Daily Time Record.
 */
class DtrMonthController extends Controller
{
    /**
     * Confirm the month's finished days as reviewed (required before payroll).
     * For a month that has ended, this also posts the month's leave credits
     * and deducts tardiness and undertime from vacation leave.
     */
    public function validateMonth(MonthRequest $request, Employee $employee, DtrCorrectionService $corrections, LeaveCreditPostingService $posting): RedirectResponse
    {
        Gate::authorize('manageAttendance', $employee);

        $month = $request->month();
        $count = $corrections->validateMonth($employee, $month, $request->user());

        $message = trans_choice('{0} All days of :month were already validated.|{1} :count day of :month validated.|[2,*] :count days of :month validated.', $count, [
            'count' => $count,
            'month' => $month->format('F Y'),
        ]);

        $result = $posting->postMonth($employee, $month, $request->user());

        if ($result->wasPosted()) {
            $message .= ' '.__('Leave credits posted: :earned days earned; :deducted days deducted from vacation leave for tardiness and undertime.', [
                'earned' => LeaveDays::format($result->earned),
                'deducted' => LeaveDays::format($result->tardiness),
            ]);

            if ($result->tardinessWithoutPay > 0) {
                $message .= ' '.__(':days days of tardiness exceeded vacation leave and are without pay.', ['days' => LeaveDays::format($result->tardinessWithoutPay)]);
            }
        }

        return redirect()
            ->route('attendance.dtr.show', [$employee, 'month' => $month->format('Y-m')])
            ->with('status', $message);
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
