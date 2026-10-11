<?php

namespace App\Http\Controllers\Leave;

use App\Enums\LeaveLedgerEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveAdjustmentRequest;
use App\Models\Employee;
use App\Services\Leave\LeaveDays;
use App\Services\Leave\LeaveLedger;
use Illuminate\Http\RedirectResponse;

/**
 * A manual correction to the leave card, with the reason recorded.
 */
class LeaveAdjustmentController extends Controller
{
    public function __invoke(LeaveAdjustmentRequest $request, Employee $employee, LeaveLedger $ledger): RedirectResponse
    {
        $days = LeaveDays::toThousandths($request->validated('days'));
        $ledgerType = $request->ledgerType();

        $ledger->post(
            $employee,
            $ledgerType,
            LeaveLedgerEntryType::Adjustment,
            $days,
            __('Adjustment'),
            $request->entryDate(),
            user: $request->user(),
            remarks: $request->validated('reason'),
        );

        return redirect()
            ->route('leave.card', $employee)
            ->with('status', __(':days days :direction :type.', [
                'days' => LeaveDays::format(abs($days)),
                'direction' => $days > 0 ? __('added to') : __('deducted from'),
                'type' => mb_strtolower($ledgerType->name),
            ]));
    }
}
