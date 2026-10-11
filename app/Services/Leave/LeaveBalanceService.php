<?php

namespace App\Services\Leave;

use App\Enums\LeaveCreditSource;
use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Collection;

/**
 * Real-time leave balances for display and for checking new applications.
 *
 * "Balance" is what the leave card shows. "Available" also sets aside the
 * days of applications still waiting for a decision. Sick leave beyond the
 * sick leave balance is set aside from vacation leave.
 */
class LeaveBalanceService
{
    public function __construct(private LeaveLedger $ledger) {}

    /**
     * @return array{
     *     vacation: array{balance: int, pending: int, available: int},
     *     sick: array{balance: int, pending: int, available: int},
     *     allotments: Collection<int, array{type: LeaveType, allowance: int, used: int, remaining: int}>,
     * }
     */
    public function summary(Employee $employee, ?int $year = null): array
    {
        $year ??= now()->year;

        $vacationBalance = $this->ledger->balance($employee, LeaveType::vacation());
        $sickBalance = $this->ledger->balance($employee, LeaveType::sick());

        $open = $employee->leaveRequests()
            ->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::Endorsed])
            ->with('leaveType')
            ->get();

        $pendingFor = fn (LeaveCreditSource $source): int => $open
            ->filter(fn (LeaveRequest $request): bool => $request->leaveType->credit_source === $source)
            ->sum(fn (LeaveRequest $request): int => LeaveDays::toThousandths($request->days));

        $pendingVacation = $pendingFor(LeaveCreditSource::VacationCredits);
        $pendingSick = $pendingFor(LeaveCreditSource::SickCredits);
        $sickOverflow = max(0, $pendingSick - max(0, $sickBalance));

        $allotments = LeaveType::query()->active()->ordered()->whereNotNull('annual_days')->get()
            ->map(function (LeaveType $type) use ($employee, $year): array {
                $allowance = LeaveDays::toThousandths($type->annual_days);
                $used = $this->usedInYear($employee, $type, $year);

                return ['type' => $type, 'allowance' => $allowance, 'used' => $used, 'remaining' => max(0, $allowance - $used)];
            });

        return [
            'vacation' => [
                'balance' => $vacationBalance,
                'pending' => $pendingVacation + $sickOverflow,
                'available' => $vacationBalance - $pendingVacation - $sickOverflow,
            ],
            'sick' => [
                'balance' => $sickBalance,
                'pending' => $pendingSick,
                'available' => max(0, $sickBalance - $pendingSick),
            ],
            'allotments' => $allotments,
        ];
    }

    /**
     * Days of a leave type applied for (waiting or approved) that start in the
     * given year, in thousandths.
     */
    public function usedInYear(Employee $employee, LeaveType $type, int $year, ?LeaveRequest $except = null): int
    {
        return (int) $employee->leaveRequests()
            ->holdingDates()
            ->where('leave_type_id', $type->id)
            ->whereYear('date_from', $year)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->get(['days'])
            ->sum(fn (LeaveRequest $request): int => LeaveDays::toThousandths($request->days));
    }
}
