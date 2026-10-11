<?php

namespace App\Http\Controllers\Leave;

use App\Enums\LeaveLedgerEntryType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveType;
use Illuminate\View\View;

/**
 * Every employee's vacation and sick leave balance, for HR.
 */
class LeaveCreditController extends Controller
{
    public function __invoke(): View
    {
        $vacationTypeId = LeaveType::vacation()->id;
        $sickTypeId = LeaveType::sick()->id;

        $employees = Employee::query()
            ->inService()
            ->with(['department', 'employmentType'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();

        $balances = LeaveBalance::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->pluck('balance', 'leave_type_id'));

        $lastPosted = LeaveLedgerEntry::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->where('entry_type', LeaveLedgerEntryType::Accrual)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, max(period) as period')
            ->pluck('period', 'employee_id');

        return view('leave.credits', [
            'employees' => $employees,
            'balances' => $balances,
            'lastPosted' => $lastPosted,
            'vacationTypeId' => $vacationTypeId,
            'sickTypeId' => $sickTypeId,
        ]);
    }
}
