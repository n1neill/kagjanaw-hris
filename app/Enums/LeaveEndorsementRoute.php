<?php

namespace App\Enums;

/**
 * Who must endorse a leave application before HR decides on it.
 */
enum LeaveEndorsementRoute: string
{
    /**
     * The head of the employee's department.
     */
    case DepartmentHead = 'department-head';

    /**
     * The Municipal Mayor, for department heads' own leave and for offices
     * without a department head in the system.
     */
    case MunicipalMayor = 'municipal-mayor';

    /**
     * Goes straight to HR (the Mayor's own leave).
     */
    case NotRequired = 'not-required';

    public function label(): string
    {
        return match ($this) {
            self::DepartmentHead => 'Department Head',
            self::MunicipalMayor => 'Municipal Mayor',
            self::NotRequired => 'Not required',
        };
    }
}
