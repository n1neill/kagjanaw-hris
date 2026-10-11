<?php

namespace App\Services\Leave;

use App\Enums\LeaveLedgerEntryType;
use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Mandatory (forced) leave: an employee with 10 or more days of vacation
 * leave must take at least 5 days of vacation or forced leave each year.
 * Days not taken are forfeited from vacation leave at year end, unless HR
 * restores them with an adjustment (e.g. leave cancelled for the exigency
 * of the service).
 */
class ForcedLeaveService
{
    /**
     * Vacation leave balance at which forced leave applies.
     */
    public const THRESHOLD = 10000;

    public function __construct(private LeaveLedger $ledger) {}

    /**
     * @return int The number of employees whose credits were forfeited.
     */
    public function forfeitUnused(int $year, ?User $user = null): int
    {
        $forcedType = LeaveType::query()->where('code', LeaveType::FORCED)->first();
        $required = $forcedType?->annual_days !== null ? LeaveDays::toThousandths($forcedType->annual_days) : 5000;

        $vacationType = LeaveType::vacation();
        $yearStart = CarbonImmutable::create($year)->startOfYear();
        $yearEnd = $yearStart->endOfYear()->startOfDay();
        $count = 0;

        $employees = Employee::query()
            ->whereHas('employmentType', fn ($query) => $query->where('earns_leave_credits', true))
            ->where('date_hired', '<=', $yearEnd->toDateString())
            ->where(fn ($query) => $query->whereNull('separation_date')->orWhere('separation_date', '>', $yearEnd->toDateString()))
            ->get();

        foreach ($employees as $employee) {
            $alreadyForfeited = $employee->leaveLedgerEntries()
                ->where('entry_type', LeaveLedgerEntryType::Forfeiture)
                ->whereDate('period', $yearStart->toDateString())
                ->exists();

            if ($alreadyForfeited) {
                continue;
            }

            $balanceAtYearEnd = LeaveDays::toThousandths($employee->leaveLedgerEntries()
                ->where('leave_type_id', $vacationType->id)
                ->whereDate('entry_date', '<=', $yearEnd->toDateString())
                ->orderByDesc('id')
                ->value('balance_after'));

            if ($balanceAtYearEnd < self::THRESHOLD) {
                continue;
            }

            $taken = $employee->leaveRequests()
                ->where('status', LeaveRequestStatus::Approved)
                ->whereYear('date_from', $year)
                ->whereHas('leaveType', fn ($query) => $query->whereIn('code', [LeaveType::VACATION, LeaveType::FORCED]))
                ->get(['days_with_pay'])
                ->sum(fn (LeaveRequest $request): int => LeaveDays::toThousandths($request->days_with_pay));

            $forfeited = min(max(0, $required - $taken), max(0, $this->ledger->balance($employee, $vacationType)));

            if ($forfeited > 0) {
                $this->ledger->post(
                    $employee, $vacationType, LeaveLedgerEntryType::Forfeiture, -$forfeited,
                    __('Forced leave not taken in :year', ['year' => $year]),
                    $yearEnd, period: $yearStart, user: $user,
                );
                $count++;
            }
        }

        return $count;
    }
}
