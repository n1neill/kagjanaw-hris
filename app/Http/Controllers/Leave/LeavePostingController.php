<?php

namespace App\Http\Controllers\Leave;

use App\Enums\LeaveLedgerEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeavePostingRequest;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Services\Leave\LeaveCreditPostingService;
use App\Services\Leave\LeavePostingResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Monthly posting of leave credits (earned, and tardiness/undertime
 * deductions) for employees whose DTR for the month is validated. Validating
 * a month's DTR also posts it automatically; this page shows who is still
 * waiting and why.
 */
class LeavePostingController extends Controller
{
    public function __construct(private LeaveCreditPostingService $posting) {}

    public function index(LeavePostingRequest $request): View
    {
        $month = $request->month();
        $employees = $this->posting->employeesFor($month);

        $posted = LeaveLedgerEntry::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->where('entry_type', LeaveLedgerEntryType::Accrual)
            ->whereDate('period', $month->toDateString())
            ->distinct()
            ->pluck('employee_id')
            ->flip();

        $rows = $employees->map(fn (Employee $employee): array => [
            'employee' => $employee,
            'posted' => $posted->has($employee->id),
            'reason' => $posted->has($employee->id) ? null : $this->posting->blockingReason($employee, $month),
        ]);

        return view('leave.posting', ['month' => $month, 'rows' => $rows]);
    }

    public function store(LeavePostingRequest $request): RedirectResponse
    {
        $month = $request->month();
        $results = $this->posting->postMonthForAll($month, $request->user());

        $posted = $results->filter(fn (array $row): bool => $row['result']->wasPosted())->count();
        $waiting = $results->filter(fn (array $row): bool => $row['result']->status === LeavePostingResult::SKIPPED)->count();
        $upToDate = $results->filter(fn (array $row): bool => $row['result']->status === LeavePostingResult::UP_TO_DATE)->count();

        return redirect()
            ->route('leave.posting.index', ['month' => $month->format('Y-m')])
            ->with('status', __('Leave credits for :month: :posted employees posted, :upToDate already up to date, :waiting not ready.', [
                'month' => $month->format('F Y'),
                'posted' => $posted,
                'upToDate' => $upToDate,
                'waiting' => $waiting,
            ]));
    }
}
