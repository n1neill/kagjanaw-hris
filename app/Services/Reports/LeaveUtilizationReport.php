<?php

namespace App\Services\Reports;

use App\Models\Department;
use App\Models\LeaveType;
use App\Services\Leave\LeaveDays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Leave utilization (App\Services\Leave\LeaveReportService) as an exportable report.
 */
class LeaveUtilizationReport
{
    /**
     * @param  array{
     *     byType: Collection<int, array{type: LeaveType, employees: int, days: int, with_pay: int, without_pay: int}>,
     *     byDepartment: Collection<int, array{department: string, employees: int, days: int, with_pay: int, without_pay: int}>,
     *     totals: array{employees: int, days: int, with_pay: int, without_pay: int},
     * }  $utilization
     */
    public function report(array $utilization, CarbonImmutable $from, CarbonImmutable $to, ?Department $department): Report
    {
        $days = fn (array $row): array => [$row['employees'], LeaveDays::format($row['days']), LeaveDays::format($row['with_pay']), LeaveDays::format($row['without_pay'])];
        $totals = ['Total', ...$days($utilization['totals'])];

        return new Report(
            title: __('Leave Utilization'),
            subtitle: $from->format('M j, Y').' to '.$to->format('M j, Y').' · '.($department?->name ?? __('All offices')),
            sections: [
                new ReportSection(
                    columns: ['Leave type', 'Employees', 'Days taken', 'With pay', 'Without pay'],
                    rows: $utilization['byType']->map(fn (array $row): array => [$row['type']->name, ...$days($row)])->all(),
                    totals: $totals,
                    numberColumns: [1, 2, 3, 4],
                    heading: __('By leave type'),
                ),
                new ReportSection(
                    columns: ['Office', 'Employees', 'Days taken', 'With pay', 'Without pay'],
                    rows: $utilization['byDepartment']->map(fn (array $row): array => [$row['department'], ...$days($row)])->all(),
                    totals: $totals,
                    numberColumns: [1, 2, 3, 4],
                    heading: __('By office'),
                ),
            ],
            filename: sprintf('leave-utilization-%s-to-%s', $from->format('Y-m-d'), $to->format('Y-m-d')),
            landscape: false,
        );
    }
}
