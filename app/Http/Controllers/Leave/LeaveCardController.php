<?php

namespace App\Http\Controllers\Leave;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * An employee's leave card: every vacation and sick leave entry with the
 * running balance, laid out like the CSC leave card.
 */
class LeaveCardController extends Controller
{
    public function __invoke(Request $request, Employee $employee, LeaveBalanceService $balances): View
    {
        Gate::authorize('viewLeaveCard', $employee);

        $employee->load(['department', 'position', 'employmentType']);

        return view('leave.card', [
            'employee' => $employee,
            'entries' => $employee->leaveLedgerEntries()
                ->with(['leaveType', 'creator'])
                ->orderBy('entry_date')
                ->orderBy('id')
                ->get(),
            'summary' => $balances->summary($employee),
            'vacationTypeId' => LeaveType::vacation()->id,
            'canManage' => $request->user()->can(Permission::ManageLeaveCredits),
        ]);
    }
}
