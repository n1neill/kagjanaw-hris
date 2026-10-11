<?php

namespace App\Services\Attendance;

use App\Enums\DtrStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequestDay;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Monthly attendance totals per employee, from Daily Time Records.
 */
class AttendanceReportService
{
    /**
     * @param  Builder<Employee>  $employees  The employees to include.
     * @return Collection<int, array{employee: Employee, working_days: int, present_days: int, absent_days: float, late_count: int, late_minutes: int, undertime_minutes: int, missing_punch_days: int}>
     */
    public function monthlySummary(Builder $employees, CarbonImmutable $month): Collection
    {
        $rules = WorkSchedule::current()->rules();
        $fullDayMinutes = $rules->amLength() + $rules->pmLength();

        $nonWorking = [DtrStatus::Holiday->value, DtrStatus::RestDay->value, DtrStatus::Pending->value];

        $totals = DailyTimeRecord::query()
            ->whereBetween('work_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->whereIn('employee_id', (clone $employees)->select('id'))
            ->groupBy('employee_id')
            ->selectRaw('employee_id')
            ->selectRaw('sum(case when status not in (?, ?, ?) then 1 else 0 end) as working_days', $nonWorking)
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as present_days', [DtrStatus::Present->value])
            ->selectRaw('sum(absent_minutes) as absent_minutes')
            ->selectRaw('sum(case when late_minutes > 0 then 1 else 0 end) as late_count')
            ->selectRaw('sum(late_minutes) as late_minutes')
            ->selectRaw('sum(undertime_minutes) as undertime_minutes')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as missing_punch_days', [DtrStatus::Incomplete->value])
            ->get()
            ->keyBy('employee_id');

        return (clone $employees)
            ->with(['department', 'position'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get()
            ->map(function (Employee $employee) use ($totals, $fullDayMinutes): array {
                $row = $totals->get($employee->id);

                return [
                    'employee' => $employee,
                    'working_days' => (int) ($row->working_days ?? 0),
                    'present_days' => (int) ($row->present_days ?? 0),
                    'absent_days' => round(((int) ($row->absent_minutes ?? 0)) / $fullDayMinutes, 1),
                    'late_count' => (int) ($row->late_count ?? 0),
                    'late_minutes' => (int) ($row->late_minutes ?? 0),
                    'undertime_minutes' => (int) ($row->undertime_minutes ?? 0),
                    'missing_punch_days' => (int) ($row->missing_punch_days ?? 0),
                ];
            });
    }

    /**
     * Every calendar day of a month with its DTR row (if any) and holiday,
     * plus weekly (Monday-Sunday) subtotals and month totals.
     *
     * @return array{
     *     days: Collection<int, array{date: CarbonImmutable, record: ?DailyTimeRecord, holiday: ?Holiday, leave: ?LeaveRequestDay, endsWeek: bool, week: int}>,
     *     weeks: array<int, array<string, int|float>>,
     *     totals: array<string, int|float>,
     * }
     */
    public function monthlyRecord(Employee $employee, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $records = $employee->dailyTimeRecords()
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (DailyTimeRecord $record): string => $record->work_date->toDateString());

        $holidays = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $leave = LeaveRequestDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('leaveRequest', fn ($query) => $query->where('status', LeaveRequestStatus::Approved))
            ->with('leaveRequest.leaveType')
            ->get()
            ->keyBy(fn (LeaveRequestDay $day): string => $day->date->toDateString());

        $days = collect(CarbonPeriod::create($start, $end))->map(function ($date) use ($records, $holidays, $leave, $end): array {
            $date = CarbonImmutable::parse($date);

            return [
                'date' => $date,
                'record' => $records->get($date->toDateString()),
                'holiday' => $holidays->get($date->toDateString()),
                'leave' => $leave->get($date->toDateString()),
                'endsWeek' => $date->isSunday() || $date->isSameDay($end),
                'week' => $date->isoWeek(),
            ];
        });

        $rules = WorkSchedule::current()->rules();
        $fullDayMinutes = $rules->amLength() + $rules->pmLength();

        $summarize = fn (Collection $records): array => [
            'present_days' => $records->where('status', DtrStatus::Present)->count(),
            'absent_days' => round($records->sum('absent_minutes') / $fullDayMinutes, 1),
            'late_count' => $records->filter(fn (DailyTimeRecord $record): bool => $record->late_minutes > 0)->count(),
            'late_minutes' => $records->sum('late_minutes'),
            'undertime_minutes' => $records->sum('undertime_minutes'),
            'missing_punch_days' => $records->where('status', DtrStatus::Incomplete)->count(),
            'worked_minutes' => $records->sum('worked_minutes'),
        ];

        $weeks = $days->groupBy('week')
            ->map(fn (Collection $weekDays): array => $summarize($weekDays->pluck('record')->filter()))
            ->all();

        return [
            'days' => $days,
            'weeks' => $weeks,
            'totals' => $summarize($records->values()),
        ];
    }

    /**
     * "2 h 05 m" style duration for reports.
     */
    public static function formatMinutes(int $minutes): string
    {
        if ($minutes === 0) {
            return '—';
        }

        return $minutes >= 60
            ? sprintf('%d h %02d m', intdiv($minutes, 60), $minutes % 60)
            : sprintf('%d m', $minutes);
    }
}
