<?php

namespace App\Services\Reports;

use App\Models\Department;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The monthly attendance summary (App\Services\Attendance\AttendanceReportService) as an exportable report.
 */
class AttendanceSummaryReport
{
    /**
     * @param  Collection<int, array{employee: Employee, working_days: int, present_days: int, absent_days: float, late_count: int, late_minutes: int, undertime_minutes: int, missing_punch_days: int}>  $rows
     */
    public function report(Collection $rows, CarbonImmutable $month, ?Department $department): Report
    {
        return new Report(
            title: __('Attendance Summary'),
            subtitle: $month->format('F Y').' · '.($department?->name ?? __('All offices')),
            sections: [new ReportSection(
                columns: ['Employee ID', 'Name', 'Department', 'Working days', 'Present', 'Absences (days)', 'Times late', 'Late (minutes)', 'Undertime (minutes)', 'Missing punches'],
                rows: $rows->map(fn (array $row): array => [
                    $row['employee']->employee_number,
                    $row['employee']->sortable_name,
                    $row['employee']->department->code,
                    $row['working_days'],
                    $row['present_days'],
                    $row['absent_days'],
                    $row['late_count'],
                    $row['late_minutes'],
                    $row['undertime_minutes'],
                    $row['missing_punch_days'],
                ])->values()->all(),
                numberColumns: range(3, 9),
            )],
            filename: 'attendance-summary-'.$month->format('Y-m').($department ? '-'.$department->code : ''),
        );
    }
}
