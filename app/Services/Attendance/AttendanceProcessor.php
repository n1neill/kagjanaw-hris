<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceLogStatus;
use App\Enums\LeavePortion;
use App\Enums\LeaveRequestStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequestDay;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Turns valid raw punches into Daily Time Records.
 *
 * Processing is idempotent and may be repeated at any time. It never touches
 * future dates, dates outside the employee's service, or records that HR has
 * corrected by hand.
 */
class AttendanceProcessor
{
    public function __construct(
        private PunchAssigner $assigner,
        private DtrCalculator $calculator,
    ) {}

    /**
     * Process every employee who was in service at some point in the range.
     *
     * @return int The number of employees processed.
     */
    public function processAllEmployees(CarbonInterface $from, CarbonInterface $to): int
    {
        $employees = Employee::query()
            ->where('date_hired', '<=', $to->toDateString())
            ->where(fn ($query) => $query->whereNull('separation_date')->orWhere('separation_date', '>=', $from->toDateString()))
            ->get();

        foreach ($employees as $employee) {
            $this->processEmployee($employee, $from, $to);
        }

        return $employees->count();
    }

    /**
     * @param  array<int, list<string>>  $datesByEmployeeId  Y-m-d dates to process, keyed by employee ID.
     */
    public function processDates(array $datesByEmployeeId): void
    {
        $employees = Employee::query()->whereKey(array_keys($datesByEmployeeId))->get()->keyBy('id');

        foreach ($datesByEmployeeId as $employeeId => $dates) {
            if ($employee = $employees->get($employeeId)) {
                $this->processEmployeeDates($employee, collect($dates)->unique()->sort()->values());
            }
        }
    }

    public function processEmployee(Employee $employee, CarbonInterface $from, CarbonInterface $to): void
    {
        $dates = collect(CarbonPeriod::create($from->toDateString(), $to->toDateString()))
            ->map(fn (CarbonInterface $date): string => $date->toDateString());

        $this->processEmployeeDates($employee, $dates);
    }

    /**
     * Whether the date is a working day, holiday or rest day under the schedule.
     */
    public function dayTypeFor(CarbonInterface $date, WorkSchedule $schedule, ?Holiday $holiday = null): DayType
    {
        $holiday ??= Holiday::query()->whereDate('date', $date->toDateString())->first();

        return match (true) {
            $holiday !== null => DayType::Holiday,
            ! $schedule->isWorkingDay($date->dayOfWeekIso) => DayType::RestDay,
            default => DayType::Working,
        };
    }

    /**
     * Minutes after midnight now, if the date is today; null for past dates.
     */
    public function nowMinutesFor(CarbonInterface $date): ?int
    {
        $now = CarbonImmutable::now();

        return $date->isSameDay($now) ? $now->hour * 60 + $now->minute : null;
    }

    /**
     * @param  Collection<int, string>  $dates  Sorted Y-m-d dates.
     */
    private function processEmployeeDates(Employee $employee, Collection $dates): void
    {
        $today = CarbonImmutable::today();

        $dates = $dates
            ->map(fn (string $date): CarbonImmutable => CarbonImmutable::parse($date))
            ->filter(fn (CarbonImmutable $date): bool => $date->lessThanOrEqualTo($today) && $employee->wasEmployedOn($date))
            ->values();

        if ($dates->isEmpty()) {
            return;
        }

        $from = $dates->first()->toDateString();
        $to = $dates->last()->toDateString();

        $schedule = WorkSchedule::current();
        $rules = $schedule->rules();
        $duplicateTapMinutes = (int) config('attendance.duplicate_tap_minutes');

        $holidays = Holiday::query()->whereBetween('date', [$from, $to])->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $punchesByDate = $employee->attendanceLogs()
            ->where('status', AttendanceLogStatus::Valid)
            ->whereBetween('logged_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->pluck('logged_at')
            ->groupBy(fn (CarbonInterface $loggedAt): string => $loggedAt->toDateString());

        $existing = $employee->dailyTimeRecords()
            ->whereBetween('work_date', [$from, $to])
            ->get()
            ->keyBy(fn (DailyTimeRecord $record): string => $record->work_date->toDateString());

        $leaveByDate = $this->approvedLeavePortions($employee, $from, $to);

        foreach ($dates as $date) {
            $key = $date->toDateString();
            $record = $existing->get($key);

            if ($record?->is_adjusted) {
                continue;
            }

            $minutes = ($punchesByDate->get($key) ?? collect())
                ->map(fn (CarbonInterface $loggedAt): int => $loggedAt->hour * 60 + $loggedAt->minute)
                ->all();

            $punches = $this->assigner->assign($minutes, $rules, $duplicateTapMinutes);
            $result = $this->calculator->calculate(
                $punches,
                $rules,
                $this->dayTypeFor($date, $schedule, $holidays->get($key)),
                $this->nowMinutesFor($date),
                LeaveCoverage::fromPortions($leaveByDate->get($key) ?? []),
            );

            ($record ?? new DailyTimeRecord(['employee_id' => $employee->id, 'work_date' => $key]))
                ->fill([...$punches->toTimes(), ...$this->resultAttributes($result)])
                ->save();
        }
    }

    /**
     * The sessions of a date covered by the employee's approved leave.
     */
    public function leaveCoverageFor(Employee $employee, CarbonInterface $date): LeaveCoverage
    {
        $key = $date->toDateString();

        return LeaveCoverage::fromPortions($this->approvedLeavePortions($employee, $key, $key)->get($key) ?? []);
    }

    /**
     * @return Collection<string, list<LeavePortion>> Approved leave portions keyed by Y-m-d.
     */
    private function approvedLeavePortions(Employee $employee, string $from, string $to): Collection
    {
        return LeaveRequestDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$from, $to])
            ->whereHas('leaveRequest', fn ($query) => $query->where('status', LeaveRequestStatus::Approved))
            ->get(['date', 'portion'])
            ->groupBy(fn (LeaveRequestDay $day): string => $day->date->toDateString())
            ->map(fn (Collection $days): array => $days->pluck('portion')->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function resultAttributes(DtrResult $result): array
    {
        return [
            'status' => $result->status,
            'late_minutes' => $result->lateMinutes,
            'undertime_minutes' => $result->undertimeMinutes,
            'absent_minutes' => $result->absentMinutes,
            'worked_minutes' => $result->workedMinutes,
        ];
    }
}
