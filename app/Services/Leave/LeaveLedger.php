<?php

namespace App\Services\Leave;

use App\Enums\LeaveLedgerEntryType;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The only way vacation and sick leave balances change. Each posting adds a
 * line to the leave card and updates the balance row while holding a row
 * lock, so two postings for the same employee cannot interleave.
 */
class LeaveLedger
{
    /**
     * @param  int  $days  Thousandths of a day: positive adds credits, negative uses them.
     * @param  int  $withoutPay  Thousandths of a day of absence without pay recorded on this line.
     */
    public function post(
        Employee $employee,
        LeaveType $ledgerType,
        LeaveLedgerEntryType $entryType,
        int $days,
        string $particulars,
        CarbonInterface $entryDate,
        ?CarbonInterface $period = null,
        int $withoutPay = 0,
        ?LeaveRequest $leaveRequest = null,
        ?User $user = null,
        ?string $remarks = null,
    ): LeaveLedgerEntry {
        return DB::transaction(function () use ($employee, $ledgerType, $entryType, $days, $particulars, $entryDate, $period, $withoutPay, $leaveRequest, $user, $remarks): LeaveLedgerEntry {
            $balance = $this->lockBalanceRow($employee, $ledgerType);
            $newBalance = LeaveDays::toThousandths($balance->balance) + $days;

            $balance->update(['balance' => LeaveDays::toDecimal($newBalance)]);

            return LeaveLedgerEntry::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $ledgerType->id,
                'entry_type' => $entryType,
                'entry_date' => $entryDate->toDateString(),
                'period' => $period?->toDateString(),
                'days' => LeaveDays::toDecimal($days),
                'balance_after' => LeaveDays::toDecimal($newBalance),
                'days_without_pay' => LeaveDays::toDecimal($withoutPay),
                'leave_request_id' => $leaveRequest?->id,
                'particulars' => $particulars,
                'remarks' => $remarks,
                'created_by' => $user?->id,
            ]);
        });
    }

    /**
     * The current balance in thousandths, without locking.
     */
    public function balance(Employee $employee, LeaveType $ledgerType): int
    {
        return LeaveDays::toThousandths(
            LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $ledgerType->id)
                ->value('balance')
        );
    }

    /**
     * The current balance in thousandths, locked until the surrounding
     * transaction ends. Call inside DB::transaction().
     */
    public function lockedBalance(Employee $employee, LeaveType $ledgerType): int
    {
        return LeaveDays::toThousandths($this->lockBalanceRow($employee, $ledgerType)->balance);
    }

    private function lockBalanceRow(Employee $employee, LeaveType $ledgerType): LeaveBalance
    {
        LeaveBalance::query()->firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $ledgerType->id],
            ['balance' => '0.000'],
        );

        return LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $ledgerType->id)
            ->lockForUpdate()
            ->sole();
    }
}
