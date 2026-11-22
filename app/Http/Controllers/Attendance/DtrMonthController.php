<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\MonthRequest;
use App\Models\Employee;
use App\Services\Approvals\ChangeRequestService;
use App\Services\Leave\LeaveDays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Month-level HR actions on a Daily Time Record.
 */
class DtrMonthController extends Controller
{
    use SendsChangesForApproval;

    /**
     * Confirm the month's finished days as reviewed (required before payroll).
     * For a month that has ended, this also posts the month's leave credits
     * and deducts tardiness and undertime from vacation leave.
     */
    public function validateMonth(MonthRequest $request, Employee $employee, ChangeRequestService $changes): RedirectResponse
    {
        Gate::authorize('manageAttendance', $employee);

        $month = $request->month();
        $outcome = $changes->perform($request->user(), ChangeAction::ValidateDtrMonth, ['month' => $month->format('Y-m')], $employee);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('attendance.dtr.show', [$employee, 'month' => $month->format('Y-m')]));
        }

        ['count' => $count, 'posting' => $result] = $outcome->result;

        $message = trans_choice('{0} All days of :month were already validated.|{1} :count day of :month validated.|[2,*] :count days of :month validated.', $count, [
            'count' => $count,
            'month' => $month->format('F Y'),
        ]);

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
    public function reprocess(MonthRequest $request, Employee $employee, ChangeRequestService $changes): RedirectResponse
    {
        Gate::authorize('manageAttendance', $employee);

        $month = $request->month();
        $outcome = $changes->perform($request->user(), ChangeAction::ReprocessDtrMonth, ['month' => $month->format('Y-m')], $employee);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('attendance.dtr.show', [$employee, 'month' => $month->format('Y-m')]));
        }

        return redirect()
            ->route('attendance.dtr.show', [$employee, 'month' => $month->format('Y-m')])
            ->with('status', __('The DTR for :month was recomputed from the attendance logs.', ['month' => $month->format('F Y')]));
    }
}
