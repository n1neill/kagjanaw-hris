<?php

namespace App\Services;

use App\Enums\DtrStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\PayrollRunStatus;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data for the dashboard charts, shaped for the <x-chart> component.
 *
 * Each chart is a type (how resources/js/charts.js draws it), a unit for the
 * values, category labels, and one or more series. A series names its colour
 * by role (ontime, late, leave, absent, primary, secondary), never by hex,
 * so the theme decides the actual colour in light and dark mode.
 *
 * @phpstan-type ChartSeries array{label: string, color: string, values: list<int|float>}
 * @phpstan-type ChartData array{type: string, unit: string, labels: list<string>, series: list<ChartSeries>}
 */
class DashboardChartService
{
    /**
     * Categories past this many are folded into "Other".
     */
    private const MAX_CATEGORIES = 8;

    /**
     * The attendance categories in stacking order. Green and red are kept
     * apart so neighbouring segments stay distinct for colour-blind readers.
     *
     * @var array<string, array{label: string, color: string}>
     */
    private const ATTENDANCE_SERIES = [
        'ontime' => ['label' => 'On time', 'color' => 'ontime'],
        'late' => ['label' => 'Late', 'color' => 'late'],
        'leave' => ['label' => 'On leave', 'color' => 'leave'],
        'absent' => ['label' => 'Absent', 'color' => 'absent'],
    ];

    /**
     * Employees on time, late, on leave and absent on each working day of
     * this month so far, for every office or for one department.
     *
     * @return ChartData
     */
    public function attendanceTrend(?int $departmentId = null): array
    {
        $rows = $this->attendanceRows()
            ->when($departmentId !== null, fn ($query) => $query->whereHas(
                'employee',
                fn ($employees) => $employees->where('department_id', $departmentId),
            ))
            ->get();

        $days = $rows->groupBy(fn (DailyTimeRecord $row): string => $row->work_date->toDateString())->sortKeys();

        $series = [];

        foreach (self::ATTENDANCE_SERIES as $key => $definition) {
            $series[] = [
                'label' => __($definition['label']),
                'color' => $definition['color'],
                'values' => $days->map(fn (Collection $dayRows): int => $this->countFor($dayRows, $key))->values()->all(),
            ];
        }

        return [
            'type' => 'column-stacked',
            'unit' => 'employees',
            'labels' => $days->keys()->map(fn (string $date): string => CarbonImmutable::parse($date)->format('M j'))->all(),
            'series' => $series,
        ];
    }

    /**
     * How the employee's working days this month so far were spent, as one
     * stacked bar.
     *
     * @return ChartData
     */
    public function myAttendanceMonth(Employee $employee): array
    {
        $rows = $this->attendanceRows()->where('employee_id', $employee->id)->get();

        $series = [];

        foreach (self::ATTENDANCE_SERIES as $key => $definition) {
            $series[] = [
                'label' => __($definition['label']),
                'color' => $definition['color'],
                'values' => [$this->countFor($rows, $key)],
            ];
        }

        return [
            'type' => 'bar-stacked',
            'unit' => 'days',
            'labels' => [now()->format('F')],
            'series' => $series,
        ];
    }

    /**
     * Days of approved leave this year by leave type, for every office or
     * for one department.
     *
     * @return ChartData
     */
    public function leaveDaysByType(?int $departmentId = null): array
    {
        $year = now()->year;

        $totals = LeaveRequest::query()
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.status', LeaveRequestStatus::Approved)
            ->whereBetween('leave_requests.date_from', ["{$year}-01-01", "{$year}-12-31"])
            ->when($departmentId !== null, fn ($query) => $query->whereHas(
                'employee',
                fn ($employees) => $employees->where('department_id', $departmentId),
            ))
            ->groupBy('leave_types.name')
            ->selectRaw('leave_types.name as label, sum(leave_requests.days) as total')
            ->toBase()
            ->pluck('total', 'label')
            ->map(fn (string $days): float => round((float) $days, 1));

        return $this->singleSeries($totals->all(), __('Days of leave'), 'days');
    }

    /**
     * Employees in service in each office.
     *
     * @return ChartData
     */
    public function headcountByDepartment(): array
    {
        $counts = Employee::query()
            ->inService()
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->groupBy('departments.name')
            ->selectRaw('departments.name as label, count(*) as total')
            ->toBase()
            ->pluck('total', 'label')
            ->map(fn (int|string $total): int => (int) $total);

        return $this->singleSeries($counts->all(), __('Employees'), 'employees');
    }

    /**
     * Gross and net pay of the last six pay periods with an approved or
     * released payroll, both pay bases together.
     *
     * @return ChartData
     */
    public function payrollTotals(): array
    {
        $periods = PayrollItem::query()
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.payroll_period_id')
            ->whereIn('payroll_runs.status', [PayrollRunStatus::Approved, PayrollRunStatus::Released])
            ->groupBy('payroll_periods.id', 'payroll_periods.start_date', 'payroll_periods.end_date')
            ->orderByDesc('payroll_periods.start_date')
            ->limit(6)
            ->selectRaw('payroll_periods.start_date, payroll_periods.end_date, sum(payroll_items.gross_pay) as gross, sum(payroll_items.net_pay) as net')
            ->toBase()
            ->get()
            ->reverse()
            ->values();

        return [
            'type' => 'column',
            'unit' => 'peso',
            'labels' => $periods->map(fn (object $period): string => CarbonImmutable::parse($period->start_date)->format('M j').'–'.CarbonImmutable::parse($period->end_date)->format('j'))->all(),
            'series' => [
                ['label' => __('Gross pay'), 'color' => 'primary', 'values' => $periods->map(fn (object $period): float => round((float) $period->gross, 2))->all()],
                ['label' => __('Net pay'), 'color' => 'secondary', 'values' => $periods->map(fn (object $period): float => round((float) $period->net, 2))->all()],
            ],
        ];
    }

    /**
     * A horizontal bar chart of one measure per category, largest first,
     * with the smallest categories folded into "Other".
     *
     * @param  array<string, int|float>  $totals
     * @return ChartData
     */
    public function singleSeries(array $totals, string $label, string $unit): array
    {
        $sorted = collect($totals)->sortDesc();

        if ($sorted->count() > self::MAX_CATEGORIES) {
            $sorted = $sorted->take(self::MAX_CATEGORIES - 1)
                ->put(__('Other'), $sorted->slice(self::MAX_CATEGORIES - 1)->sum());
        }

        return [
            'type' => 'bar',
            'unit' => $unit,
            'labels' => $sorted->keys()->map(fn (int|string $key): string => (string) $key)->all(),
            'series' => [
                ['label' => $label, 'color' => 'primary', 'values' => $sorted->values()->all()],
            ],
        ];
    }

    /**
     * Whether a chart has anything to draw.
     *
     * @param  ChartData  $chart
     */
    public static function hasData(array $chart): bool
    {
        return collect($chart['series'])->flatMap(fn (array $series): array => $series['values'])->contains(fn (int|float $value): bool => $value > 0);
    }

    /**
     * This month's working-day records, counted by day, status and lateness.
     *
     * @return Builder<DailyTimeRecord>
     */
    private function attendanceRows(): Builder
    {
        return DailyTimeRecord::query()
            ->whereBetween('work_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->whereNotIn('status', [DtrStatus::Holiday, DtrStatus::RestDay])
            ->groupBy('work_date', 'status', 'is_late')
            ->selectRaw('work_date, status, (late_minutes > 0) as is_late, count(*) as total');
    }

    /**
     * How many of the grouped rows fall into one attendance category.
     *
     * @param  Collection<int, DailyTimeRecord>  $rows
     */
    private function countFor(Collection $rows, string $category): int
    {
        return (int) $rows->filter(fn (DailyTimeRecord $row): bool => $this->categoryOf($row) === $category)->sum('total');
    }

    /**
     * Leave and absence first; anyone who reported for work is on time or late.
     */
    private function categoryOf(DailyTimeRecord $row): string
    {
        return match (true) {
            $row->status === DtrStatus::OnLeave => 'leave',
            $row->status === DtrStatus::Absent => 'absent',
            (bool) $row->is_late => 'late',
            default => 'ontime',
        };
    }
}
