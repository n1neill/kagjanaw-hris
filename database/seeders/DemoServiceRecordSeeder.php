<?php

namespace Database\Seeders;

use App\Enums\PayBasis;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\Payroll\Money;
use App\Services\Payroll\SalarySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A plausible service record for every demo employee who has none yet.
 * Development only. Each employee's history is generated from a seed of their
 * employee number, so reseeding gives the same records.
 *
 *  - Some permanent employees first served a year or two as casuals in a
 *    lower position before their permanent appointment.
 *  - Permanent employees get a step increment every three years, up to their
 *    current step.
 *  - Job order and contract of service workers get one row per yearly contract.
 *  - The last row is open ("Present") or closed on the separation date.
 *
 * Salaries come from the current salary schedule, discounted 3% for each year
 * back, since only the current tranche is loaded.
 */
class DemoServiceRecordSeeder extends Seeder
{
    use WithoutModelEvents;

    private const STEP_INCREMENT_YEARS = 3;

    private const YEARLY_SALARY_DISCOUNT = 0.97;

    /**
     * Run the database seeds.
     */
    public function run(SalarySchedule $salarySchedule): void
    {
        $hrUserId = User::query()->where('username', '2015-0101')->value('id');
        $positions = Position::query()->whereNotNull('salary_grade')->get(['title', 'salary_grade']);
        $offices = Department::query()->orderBy('name')->pluck('name');

        Employee::query()
            ->whereDoesntHave('serviceRecords')
            ->with(['position', 'employmentType', 'department'])
            ->each(function (Employee $employee) use ($hrUserId, $positions, $offices, $salarySchedule): void {
                $random = new Randomizer(new Mt19937(crc32($employee->employee_number)));

                $periods = $employee->payBasis() === PayBasis::DailyRate
                    ? $this->contractPeriods($employee)
                    : $this->appointmentPeriods($employee, $random, $positions, $offices);

                foreach ($this->toRows($employee, $periods, $random, $salarySchedule) as $row) {
                    $employee->serviceRecords()->make($row)->forceFill(['created_by' => $hrUserId])->save();
                }
            });
    }

    /**
     * @param  Collection<int, Position>  $positions
     * @param  Collection<int, string>  $offices
     * @return list<array{from: CarbonImmutable, designation: string, status: string, grade: ?int, step: ?int, station: string, remarks: ?string}>
     */
    private function appointmentPeriods(Employee $employee, Randomizer $random, Collection $positions, Collection $offices): array
    {
        $hired = CarbonImmutable::parse($employee->date_hired);
        $end = $this->endOfService($employee);
        $isPermanent = $employee->employmentType->code === 'permanent';
        $grade = (int) $employee->salary_grade;
        $currentStep = max(1, (int) $employee->salary_step);
        $periods = [];
        $appointedOn = $hired;

        $lowerPositions = $positions
            ->filter(fn (Position $position): bool => $position->salary_grade < $grade && $position->salary_grade >= $grade - 6)
            ->values();

        if ($isPermanent && $hired->diffInYears($end) >= 4 && $lowerPositions->isNotEmpty() && $random->getInt(1, 10) <= 6) {
            $first = $lowerPositions[$random->getInt(0, $lowerPositions->count() - 1)];
            $periods[] = [
                'from' => $hired,
                'designation' => $first->title,
                'status' => 'Casual',
                'grade' => $first->salary_grade,
                'step' => 1,
                'station' => $offices[$random->getInt(0, $offices->count() - 1)],
                'remarks' => 'Original appointment',
            ];
            $appointedOn = $hired->addYears($random->getInt(1, 2))->startOfMonth();
        }

        $increments = $isPermanent
            ? min(intdiv((int) $appointedOn->diffInYears($end), self::STEP_INCREMENT_YEARS), $currentStep - 1)
            : 0;
        $firstStep = $currentStep - $increments;

        $periods[] = [
            'from' => $appointedOn,
            'designation' => $employee->position->title,
            'status' => $employee->employmentType->name,
            'grade' => $grade,
            'step' => $firstStep,
            'station' => $employee->department->name,
            'remarks' => match (true) {
                $employee->employmentType->code === 'elective' => 'Elected',
                $periods !== [] => 'Promotion; permanent appointment',
                default => 'Original appointment',
            },
        ];

        for ($increment = 1; $increment <= $increments; $increment++) {
            $from = $appointedOn->addYears($increment * self::STEP_INCREMENT_YEARS);

            if ($from->greaterThanOrEqualTo($end)) {
                break;
            }

            $periods[] = [
                'from' => $from,
                'designation' => $employee->position->title,
                'status' => $employee->employmentType->name,
                'grade' => $grade,
                'step' => $firstStep + $increment,
                'station' => $employee->department->name,
                'remarks' => 'Step increment (length of service)',
            ];
        }

        return $periods;
    }

    /**
     * One period per yearly job order or contract of service.
     *
     * @return list<array{from: CarbonImmutable, designation: string, status: string, grade: ?int, step: ?int, station: string, remarks: ?string}>
     */
    private function contractPeriods(Employee $employee): array
    {
        $from = CarbonImmutable::parse($employee->date_hired);
        $end = $this->endOfService($employee);
        $remarks = 'Daily rate ₱'.Money::format($employee->daily_rate).'; not counted as government service';
        $periods = [];

        while ($from->lessThanOrEqualTo($end)) {
            $periods[] = [
                'from' => $from,
                'designation' => $employee->position->title,
                'status' => $employee->employmentType->name,
                'grade' => null,
                'step' => null,
                'station' => $employee->department->name,
                'remarks' => $periods === [] ? 'First contract; '.$remarks : 'Contract renewed; '.$remarks,
            ];
            $from = $from->addYear()->startOfYear();
        }

        return $periods;
    }

    /**
     * Turn periods into service record rows: each period ends the day before
     * the next starts, and the last one is open or ends on separation.
     *
     * @param  list<array{from: CarbonImmutable, designation: string, status: string, grade: ?int, step: ?int, station: string, remarks: ?string}>  $periods
     * @return list<array<string, mixed>>
     */
    private function toRows(Employee $employee, array $periods, Randomizer $random, SalarySchedule $salarySchedule): array
    {
        $today = CarbonImmutable::today();
        $withLeaveWithoutPay = $random->getInt(1, 6) === 1 && count($periods) > 1 ? $random->getInt(0, count($periods) - 2) : null;
        $rows = [];

        foreach ($periods as $index => $period) {
            $next = $periods[$index + 1] ?? null;
            $isLast = $next === null;

            $rows[] = [
                'service_from' => $period['from']->toDateString(),
                'service_to' => match (true) {
                    ! $isLast => $next['from']->subDay()->toDateString(),
                    $employee->separation_date !== null => $employee->separation_date->toDateString(),
                    default => null,
                },
                'designation' => $period['designation'],
                'status' => $period['status'],
                'annual_salary' => $this->annualSalary($period, $today, $salarySchedule),
                'station' => $period['station'],
                'branch' => 'Local',
                'leave_without_pay' => $index === $withLeaveWithoutPay ? $random->getInt(2, 15).' days' : null,
                'separation_date' => $isLast ? $employee->separation_date?->toDateString() : null,
                'separation_cause' => $isLast && $employee->separation_date !== null ? $employee->employment_status->label() : null,
                'remarks' => $period['remarks'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array{from: CarbonImmutable, grade: ?int, step: ?int}  $period
     */
    private function annualSalary(array $period, CarbonImmutable $today, SalarySchedule $salarySchedule): ?string
    {
        if ($period['grade'] === null || $period['step'] === null) {
            return null;
        }

        $monthly = $salarySchedule->monthlySalary($period['grade'], $period['step'], $today);

        if ($monthly === null) {
            return null;
        }

        $yearsBack = max(0, $today->year - $period['from']->year);
        $monthlyThen = (int) round($monthly * (self::YEARLY_SALARY_DISCOUNT ** $yearsBack) / 100) * 100;

        return Money::toDecimal($monthlyThen * 12);
    }

    private function endOfService(Employee $employee): CarbonImmutable
    {
        return $employee->separation_date !== null
            ? CarbonImmutable::parse($employee->separation_date)
            : CarbonImmutable::today();
    }
}
