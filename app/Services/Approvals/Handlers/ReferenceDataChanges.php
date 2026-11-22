<?php

namespace App\Services\Approvals\Handlers;

use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Services\Approvals\ChangeContext;

/**
 * Applies changes to departments, positions and employment types.
 */
class ReferenceDataChanges
{
    public function createDepartment(ChangeContext $change): Department
    {
        return Department::create($change->payload);
    }

    public function updateDepartment(ChangeContext $change): Department
    {
        $department = $change->subject(Department::class);
        $department->update($change->payload);

        return $department;
    }

    public function createPosition(ChangeContext $change): Position
    {
        return Position::create($change->payload);
    }

    public function updatePosition(ChangeContext $change): Position
    {
        $position = $change->subject(Position::class);
        $position->update($change->payload);

        return $position;
    }

    public function createEmploymentType(ChangeContext $change): EmploymentType
    {
        return EmploymentType::create($change->payload);
    }

    public function updateEmploymentType(ChangeContext $change): EmploymentType
    {
        $employmentType = $change->subject(EmploymentType::class);
        $employmentType->update($change->payload);

        return $employmentType;
    }
}
