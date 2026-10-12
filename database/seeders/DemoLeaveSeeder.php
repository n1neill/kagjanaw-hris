<?php

namespace Database\Seeders;

use App\Enums\DtrStatus;
use App\Enums\LeaveLedgerEntryType;
use App\Enums\LeavePortion;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Attendance\DtrCorrectionService;
use App\Services\Leave\LeaveCreditPostingService;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeaveRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Leave cards and applications for the demo, built through the real
 * services so every number is consistent. Development only.
 *
 *  1. Opening balances as of the end of the month before last, sized to each
 *     employee's years of service.
 *  2. An approved sick leave last month, so the DTR shows a day on leave.
 *  3. Last month's missing punches corrected, every DTR validated and the
 *     month's credits posted (earned, and tardiness/undertime deducted).
 *  4. Applications in every state of the workflow.
 */
class DemoLeaveSeeder extends Seeder
{
    private const DEMO_REASON = 'Demo data: confirmed against the office logbook.';

    /**
     * Run the database seeds.
     */
    public function run(
        LeaveLedger $ledger,
        LeaveRequestService $leave,
        DtrCorrectionService $corrections,
        LeaveCreditPostingService $posting,
    ): void {
        if (LeaveRequest::query()->exists()) {
            return;
        }

        $lastMonth = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $asOf = $lastMonth->subDay();

        $hr = User::query()->where('username', '2015-0101')->sole();
        $head = Employee::query()->where('employee_number', '2010-0045')->sole();
        $employee = Employee::query()->where('employee_number', '2021-0230')->sole();
        $mayor = Employee::query()->where('employee_number', '2022-0001')->sole();

        $this->seedOpeningBalances($ledger, $asOf, $hr);

        // A sick day last month, filed after the fact and approved.
        $sickDay = $this->firstWorkingDay($lastMonth->addDays(9));
        $sick = $leave->file($employee, $this->type('SL'), $sickDay, $sickDay, LeavePortion::FullDay, 'Fever; rested at home.', null, $employee->user);
        $leave->approve($leave->endorse($sick, $head->user, 'Noted.'), $hr, null);

        $this->validateAndPost($lastMonth, $corrections, $posting, $hr);

        $nextWeek = $this->firstWorkingDay(CarbonImmutable::today()->addDays(8));

        // Waiting for the department head.
        $leave->file($employee, $this->type('VL'), $nextWeek, $this->addWorkingDays($nextWeek, 2), LeavePortion::FullDay, 'Family vacation within the Philippines.', null, $employee->user);

        // Withdrawn by the employee before the department head acted.
        $withdrawn = $leave->file($employee, $this->type('VL'), $this->addWorkingDays($nextWeek, 8), $this->addWorkingDays($nextWeek, 8), LeavePortion::FullDay, 'Attend a wedding in Butuan City.', null, $employee->user);
        $leave->cancel($withdrawn, $employee->user, 'The wedding was moved to next year.');

        // A department head's own leave, waiting for the Mayor.
        $leave->file($head, $this->type('VL'), $this->addWorkingDays($nextWeek, 5), $this->addWorkingDays($nextWeek, 6), LeavePortion::FullDay, 'Personal matters in Surigao City.', null, $head->user);

        // The Mayor's own leave, straight to HR.
        $leave->file($mayor, $this->type('SPL'), $this->addWorkingDays($nextWeek, 3), $this->addWorkingDays($nextWeek, 3), LeavePortion::FullDay, 'Personal transaction.', null, $mayor->user);

        $staff = Employee::query()
            ->where('department_id', $head->department_id)
            ->whereNotIn('id', [$head->id, $employee->id])
            ->inService()
            ->with('user', 'employmentType')
            ->get()
            ->filter(fn (Employee $member): bool => $member->user !== null && $member->earnsLeaveCredits())
            ->values();

        if ($staff->count() >= 2) {
            // Endorsed, waiting for HR.
            $endorsed = $leave->file($staff[0], $this->type('VL'), $this->addWorkingDays($nextWeek, 1), $this->addWorkingDays($nextWeek, 1), LeavePortion::Morning, 'Enrollment of child.', null, $staff[0]->user);
            $leave->endorse($endorsed, $head->user, 'Recommended for approval.');

            // Rejected by the department head.
            $rejected = $leave->file($staff[1], $this->type('VL'), $this->addWorkingDays($nextWeek, 4), $this->addWorkingDays($nextWeek, 4), LeavePortion::FullDay, 'Out-of-town trip.', null, $staff[1]->user);
            $leave->reject($rejected, $head->user, 'Immunization outreach scheduled that day; please choose another date.');
        }
    }

    /**
     * Balances from the paper leave cards, about 15 days of each a year of
     * service less leave already used, capped for long-serving employees.
     */
    private function seedOpeningBalances(LeaveLedger $ledger, CarbonImmutable $asOf, User $hr): void
    {
        $vacation = LeaveType::vacation();
        $sick = LeaveType::sick();

        Employee::query()->with('employmentType')->get()
            ->filter(fn (Employee $employee): bool => $employee->earnsLeaveCredits() && $employee->date_hired->lessThanOrEqualTo($asOf))
            ->each(function (Employee $employee) use ($ledger, $asOf, $hr, $vacation, $sick): void {
                if ($employee->leaveLedgerEntries()->exists()) {
                    return;
                }

                $random = new Randomizer(new Mt19937(crc32($employee->employee_number)));
                $years = max(0.2, $employee->date_hired->floatDiffInYears($asOf));
                $earned = (int) round(min($years, 25) * 15000);
                $particulars = __('Balance as of :date', ['date' => $asOf->format('M j, Y')]);

                $ledger->post($employee, $vacation, LeaveLedgerEntryType::OpeningBalance, $this->roundToEighth((int) ($earned * $random->getInt(35, 75) / 100)), $particulars, $asOf, user: $hr);
                $ledger->post($employee, $sick, LeaveLedgerEntryType::OpeningBalance, $this->roundToEighth((int) ($earned * $random->getInt(55, 90) / 100)), $particulars, $asOf, user: $hr);
            });
    }

    private function validateAndPost(CarbonImmutable $month, DtrCorrectionService $corrections, LeaveCreditPostingService $posting, User $hr): void
    {
        $times = ['am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00'];

        DailyTimeRecord::query()
            ->where('status', DtrStatus::Incomplete)
            ->whereBetween('work_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->with('employee')
            ->get()
            ->each(function (DailyTimeRecord $record) use ($corrections, $times, $hr): void {
                $completed = [];

                foreach ($corrections->punchTimes($record) as $field => $time) {
                    $completed[$field] = $time ?? $times[$field];
                }

                $corrections->correct($record->employee, CarbonImmutable::parse($record->work_date), $completed, self::DEMO_REASON, $hr);
            });

        foreach ($posting->employeesFor($month) as $employee) {
            try {
                $corrections->validateMonth($employee, $month, $hr);
            } catch (ValidationException) {
                continue;
            }

            $posting->postMonth($employee, $month, $hr);
        }
    }

    private function type(string $code): LeaveType
    {
        return LeaveType::query()->where('code', $code)->sole();
    }

    private function firstWorkingDay(CarbonImmutable $date): CarbonImmutable
    {
        while (! $this->isWorkingDay($date)) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function addWorkingDays(CarbonImmutable $date, int $days): CarbonImmutable
    {
        for ($added = 0; $added < $days;) {
            $date = $date->addDay();

            if ($this->isWorkingDay($date)) {
                $added++;
            }
        }

        return $date;
    }

    private function isWorkingDay(CarbonImmutable $date): bool
    {
        return ! $date->isWeekend() && ! Holiday::query()->whereDate('date', $date->toDateString())->exists();
    }

    /**
     * Paper leave cards usually carry whole and quarter days; keep balances tidy.
     */
    private function roundToEighth(int $thousandths): int
    {
        return (int) (round($thousandths / 125) * 125);
    }
}
