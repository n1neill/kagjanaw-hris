<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Permission;

/**
 * Reports cover any office (or all) for the HR Office, and only their own
 * office for department heads and the Municipal Mayor.
 */
trait ScopesReportToDepartment
{
    public function seesAllDepartments(): bool
    {
        return $this->user()->can(Permission::GenerateReports);
    }

    /**
     * The office the report covers; null means every office (HR only).
     */
    public function departmentId(): ?int
    {
        if (! $this->seesAllDepartments()) {
            return $this->user()->employee?->department_id;
        }

        $department = $this->validated('department');

        return $department === null ? null : (int) $department;
    }

    /**
     * False for a department head whose account has no office yet; such a
     * user must see an empty report, never every office's.
     */
    public function hasDepartmentScope(): bool
    {
        return $this->seesAllDepartments() || $this->departmentId() !== null;
    }
}
