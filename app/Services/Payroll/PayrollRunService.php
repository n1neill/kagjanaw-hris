<?php

namespace App\Services\Payroll;

use App\Enums\PayBasis;
use App\Enums\PayrollRunStatus;
use App\Jobs\ComputePayrollRun;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Notifications\PayslipReleasedNotification;
use App\Services\Leave\LeaveDays;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The payroll workflow, all done by HR: create a run for a pay period →
 * compute it (queued) → review → approve → release, which locks the run and
 * makes the payslips available to employees. Until approval a run can be
 * recomputed as often as needed; an approved run can be reopened.
 */
class PayrollRunService
{
    public function __construct(private PayrollItemCalculator $calculator) {}

    /**
     * @throws ValidationException when the payroll already exists.
     */
    public function create(int $year, int $month, int $half, PayBasis $payBasis, User $user): PayrollRun
    {
        return DB::transaction(function () use ($year, $month, $half, $payBasis, $user): PayrollRun {
            $period = PayrollPeriod::for($year, $month, $half);

            if ($period->runs()->where('pay_basis', $payBasis)->exists()) {
                throw ValidationException::withMessages(['period' => __('The :payroll for :period already exists.', [
                    'payroll' => mb_strtolower($payBasis->payrollLabel()),
                    'period' => $period->label(),
                ])]);
            }

            $run = new PayrollRun(['payroll_period_id' => $period->id, 'pay_basis' => $payBasis]);
            $run->forceFill(['status' => PayrollRunStatus::Draft, 'created_by' => $user->id])->save();

            return $run;
        });
    }

    /**
     * Queue the computation. The run shows "Computing…" until it is done.
     */
    public function requestComputation(PayrollRun $run, User $user): PayrollRun
    {
        $run = $this->transition($run, [PayrollRunStatus::Draft, PayrollRunStatus::Computing, PayrollRunStatus::Computed], function (PayrollRun $run) use ($user): void {
            $run->forceFill(['status' => PayrollRunStatus::Computing, 'computed_by' => $user->id, 'failure_message' => null])->save();
        });

        ComputePayrollRun::dispatch($run)->afterCommit();

        return $run;
    }

    /**
     * Replace the run's items with a fresh computation for every employee of
     * its pay basis in service during the period. Called by the queued job.
     */
    public function compute(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run): PayrollRun {
            $run = PayrollRun::query()->whereKey($run->id)->lockForUpdate()->sole();

            if ($run->status !== PayrollRunStatus::Computing) {
                return $run;
            }

            $run->load('period');
            $run->items()->delete();

            $warnings = [];
            $waiting = [];
            $totals = ['gross' => 0, 'deductions' => 0, 'net' => 0, 'count' => 0];

            foreach ($this->employeesFor($run) as $employee) {
                $result = $this->calculator->compute($employee, $run);
                $warnings = [...$warnings, ...$result['warnings']];

                foreach ($result['waiting'] as $month) {
                    $waiting[$month->format('F Y')][] = $employee->sortable_name;
                }

                if ($result['item'] === null) {
                    continue;
                }

                /** @var PayrollItem $item */
                $item = $run->items()->create(['employee_id' => $employee->id, ...$result['item']]);

                $item->deductionLines()->createMany(array_map(fn (array $line): array => [
                    'employee_deduction_id' => $line['employee_deduction_id'],
                    'label' => $line['label'],
                    'amount' => Money::toDecimal($line['amount']),
                ], $result['deductions']));

                $item->attendanceLines()->createMany(array_map(fn (array $month): array => [
                    'employee_id' => $employee->id,
                    'month' => $month['month']->toDateString(),
                    'days_without_pay' => LeaveDays::toDecimal($month['days_without_pay']),
                    'absent_minutes' => $month['absent_minutes'],
                    'salary_amount' => Money::toDecimal($month['salary_amount']),
                    'allowance_amount' => Money::toDecimal($month['allowance_amount']),
                ], $result['attendance']));

                $totals['gross'] += Money::toCentavos($item->gross_pay);
                $totals['deductions'] += Money::toCentavos($item->total_deductions);
                $totals['net'] += Money::toCentavos($item->net_pay);
                $totals['count']++;
            }

            foreach ($waiting as $month => $names) {
                $warnings[] = ['message' => $this->waitingMessage($month, $names), 'blocking' => false];
            }

            $run->forceFill([
                'status' => PayrollRunStatus::Computed,
                'employee_count' => $totals['count'],
                'total_gross' => Money::toDecimal($totals['gross']),
                'total_deductions' => Money::toDecimal($totals['deductions']),
                'total_net' => Money::toDecimal($totals['net']),
                'warnings' => $warnings ?: null,
                'computed_at' => now(),
                'failure_message' => null,
            ])->save();

            return $run;
        });
    }

    /**
     * Put a run whose computation failed back to draft, with the reason.
     */
    public function markFailed(PayrollRun $run, string $message): void
    {
        PayrollRun::query()
            ->whereKey($run->id)
            ->where('status', PayrollRunStatus::Computing)
            ->update(['status' => PayrollRunStatus::Draft->value, 'failure_message' => $message]);
    }

    /**
     * @throws ValidationException when the run still has problems to fix.
     */
    public function approve(PayrollRun $run, User $user): PayrollRun
    {
        return $this->transition($run, [PayrollRunStatus::Computed], function (PayrollRun $run) use ($user): void {
            if ($this->hasBlockingWarnings($run)) {
                throw ValidationException::withMessages(['status' => __('Resolve the problems listed under "Needs attention" and compute again before approving.')]);
            }

            $run->forceFill(['status' => PayrollRunStatus::Approved, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    /**
     * Send an approved run back for corrections.
     */
    public function reopen(PayrollRun $run): PayrollRun
    {
        return $this->transition($run, [PayrollRunStatus::Approved], function (PayrollRun $run): void {
            $run->forceFill(['status' => PayrollRunStatus::Computed, 'approved_by' => null, 'approved_at' => null])->save();
        });
    }

    /**
     * Release the payslips. The run is locked from now on.
     */
    public function release(PayrollRun $run, User $user): PayrollRun
    {
        $run = $this->transition($run, [PayrollRunStatus::Approved], function (PayrollRun $run) use ($user): void {
            $run->forceFill(['status' => PayrollRunStatus::Released, 'released_by' => $user->id, 'released_at' => now()])->save();
        });

        $run->items()->with('employee.user')->chunkById(200, function (Collection $items) use ($run): void {
            foreach ($items as $item) {
                if ($item->employee->user !== null) {
                    Notification::send($item->employee->user, new PayslipReleasedNotification($item->setRelation('run', $run)));
                }
            }
        });

        return $run;
    }

    /**
     * @throws ValidationException when the run is approved or released.
     */
    public function delete(PayrollRun $run): void
    {
        $this->transition($run, [PayrollRunStatus::Draft, PayrollRunStatus::Computed], function (PayrollRun $run): void {
            $run->delete();
        });
    }

    /**
     * One note for all employees whose DTR for a month is still unvalidated.
     *
     * @param  list<string>  $names
     */
    private function waitingMessage(string $month, array $names): string
    {
        $shown = array_slice($names, 0, 10);
        $list = implode('; ', $shown).(count($names) > count($shown) ? '; '.__('and :count more', ['count' => count($names) - count($shown)]) : '');

        return trans_choice(
            '{1} The DTR for :month is not yet validated for :count employee (:names); their absences will be deducted in a later payroll.|[2,*] The DTR for :month is not yet validated for :count employees (:names); their absences will be deducted in a later payroll.',
            count($names),
            ['month' => $month, 'count' => count($names), 'names' => $list],
        );
    }

    public function hasBlockingWarnings(PayrollRun $run): bool
    {
        return collect($run->warnings ?? [])->contains(fn (array $warning): bool => $warning['blocking']);
    }

    /**
     * Employees of the run's pay basis in service at any time in the period.
     *
     * @return Collection<int, Employee>
     */
    public function employeesFor(PayrollRun $run): Collection
    {
        $period = $run->period;

        return Employee::query()
            ->with(['employmentType', 'department', 'position', 'allowances'])
            ->whereHas('employmentType', fn ($query) => $query->where('pay_basis', $run->pay_basis))
            ->where('date_hired', '<=', $period->end_date->toDateString())
            ->where(fn ($query) => $query->whereNull('separation_date')->orWhere('separation_date', '>=', $period->start_date->toDateString()))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Run a state change on a freshly locked copy of the run, refusing it if
     * the run is no longer in one of the expected states.
     *
     * @param  list<PayrollRunStatus>  $allowed
     * @param  callable(PayrollRun): void  $change
     */
    private function transition(PayrollRun $run, array $allowed, callable $change): PayrollRun
    {
        return DB::transaction(function () use ($run, $allowed, $change): PayrollRun {
            $locked = PayrollRun::query()->whereKey($run->id)->lockForUpdate()->sole();

            if (! in_array($locked->status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => __('This payroll is :status.', ['status' => mb_strtolower($locked->status->label())])]);
            }

            $locked->load('period');
            $change($locked);

            return $locked;
        });
    }
}
