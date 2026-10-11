<?php

namespace App\Services\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Leave utilization: approved leave days taken in a period, by leave type
 * and by department. Days are counted on the dates they fall, so leave that
 * spans two months is split between them.
 */
class LeaveReportService
{
    /**
     * @return array{
     *     byType: Collection<int, array{type: LeaveType, employees: int, days: int, with_pay: int, without_pay: int}>,
     *     byDepartment: Collection<int, array{department: string, employees: int, days: int, with_pay: int, without_pay: int}>,
     *     totals: array{employees: int, days: int, with_pay: int, without_pay: int},
     * }
     */
    public function utilization(CarbonImmutable $from, CarbonImmutable $to, ?int $departmentId = null): array
    {
        $days = LeaveRequestDay::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('leaveRequest', fn ($query) => $query->where('status', LeaveRequestStatus::Approved))
            ->when($departmentId, fn ($query) => $query->whereHas('employee', fn ($employees) => $employees->where('department_id', $departmentId)))
            ->with(['leaveRequest:id,leave_type_id', 'employee:id,department_id', 'employee.department:id,name'])
            ->get();

        $summarize = fn (Collection $group): array => [
            'employees' => $group->pluck('employee_id')->unique()->count(),
            'days' => $group->sum(fn (LeaveRequestDay $day): int => LeaveDays::toThousandths($day->days)),
            'with_pay' => $group->sum(fn (LeaveRequestDay $day): int => LeaveDays::toThousandths($day->days_with_pay)),
            'without_pay' => $group->sum(fn (LeaveRequestDay $day): int => LeaveDays::toThousandths($day->days) - LeaveDays::toThousandths($day->days_with_pay)),
        ];

        $byTypeId = $days->groupBy(fn (LeaveRequestDay $day): int => $day->leaveRequest->leave_type_id);

        $byType = LeaveType::query()->ordered()->get()
            ->filter(fn (LeaveType $type): bool => $byTypeId->has($type->id))
            ->map(fn (LeaveType $type): array => ['type' => $type, ...$summarize($byTypeId->get($type->id))])
            ->values();

        $byDepartment = $days
            ->groupBy(fn (LeaveRequestDay $day): string => $day->employee->department->name)
            ->map(fn (Collection $group, string $department): array => ['department' => $department, ...$summarize($group)])
            ->sortKeys()
            ->values();

        return [
            'byType' => $byType,
            'byDepartment' => $byDepartment,
            'totals' => $summarize($days),
        ];
    }
}
