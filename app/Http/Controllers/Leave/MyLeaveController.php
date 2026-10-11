<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Services\Leave\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in employee's leave balances and applications.
 */
class MyLeaveController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, LeaveBalanceService $balances): View
    {
        $employee = $request->user()->employee;

        if ($employee === null) {
            return view('employees.no-record');
        }

        return view('leave.my', [
            'employee' => $employee,
            'summary' => $balances->summary($employee),
            'requests' => $employee->leaveRequests()
                ->with('leaveType')
                ->orderByDesc('date_from')
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
        ]);
    }
}
