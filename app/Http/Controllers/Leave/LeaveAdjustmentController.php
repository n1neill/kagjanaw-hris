<?php

namespace App\Http\Controllers\Leave;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveAdjustmentRequest;
use App\Models\Employee;
use App\Services\Approvals\ChangeRequestService;
use App\Services\Leave\LeaveDays;
use Illuminate\Http\RedirectResponse;

/**
 * A manual correction to the leave card, with the reason recorded.
 */
class LeaveAdjustmentController extends Controller
{
    use SendsChangesForApproval;

    public function __invoke(LeaveAdjustmentRequest $request, Employee $employee, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::AdjustLeaveBalance, $request->validated(), $employee);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('leave.card', $employee));
        }

        ['days' => $days, 'type' => $ledgerType] = $outcome->result;

        return redirect()
            ->route('leave.card', $employee)
            ->with('status', __(':days days :direction :type.', [
                'days' => LeaveDays::format(abs($days)),
                'direction' => $days > 0 ? __('added to') : __('deducted from'),
                'type' => mb_strtolower($ledgerType->name),
            ]));
    }
}
