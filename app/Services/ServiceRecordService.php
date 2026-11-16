<?php

namespace App\Services;

use App\Models\Employee;
use App\Services\Payroll\Money;
use App\Services\Payroll\SalarySchedule;

/**
 * Suggested values for a new service record entry, taken from the employee's
 * current appointment so HR only has to correct what differs.
 */
class ServiceRecordService
{
    public function __construct(private SalarySchedule $salarySchedule) {}

    /**
     * @return array{service_from: string, designation: ?string, status: ?string, annual_salary: ?string, station: ?string, branch: string}
     */
    public function defaultsFor(Employee $employee): array
    {
        $employee->loadMissing(['position', 'employmentType', 'department']);

        return [
            'service_from' => $employee->serviceRecords()->exists()
                ? now()->toDateString()
                : $employee->date_hired->toDateString(),
            'designation' => $employee->position?->title,
            'status' => $employee->employmentType?->name,
            'annual_salary' => $this->annualSalary($employee),
            'station' => $employee->department?->name,
            'branch' => 'Local',
        ];
    }

    /**
     * Twelve times the LGU monthly salary for the employee's grade and step
     * today, or null for employees without a salary grade (e.g. job orders).
     */
    private function annualSalary(Employee $employee): ?string
    {
        if ($employee->salary_grade === null || $employee->salary_step === null) {
            return null;
        }

        $monthly = $this->salarySchedule->monthlySalary($employee->salary_grade, $employee->salary_step, now());

        return $monthly === null ? null : Money::toDecimal($monthly * 12);
    }
}
