<?php

namespace App\Enums;

/**
 * The system roles. Case order is the precedence used to pick a user's
 * primary role when deciding which dashboard to show.
 */
enum Role: string
{
    case SystemAdministrator = 'system-administrator';
    case HrOfficer = 'hr-officer';
    case MunicipalMayor = 'municipal-mayor';
    case DepartmentHead = 'department-head';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'System Administrator',
            self::HrOfficer => 'HR Officer',
            self::MunicipalMayor => 'Municipal Mayor',
            self::DepartmentHead => 'Department Head',
            self::Employee => 'Employee',
        };
    }

    /**
     * The Blade view rendered as this role's dashboard.
     */
    public function dashboardView(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'dashboard.admin',
            self::HrOfficer => 'dashboard.hr',
            self::MunicipalMayor, self::DepartmentHead => 'dashboard.department-head',
            self::Employee => 'dashboard.employee',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $selfService = [
            Permission::ViewOwnRecords,
            Permission::ViewOwnAttendance,
            Permission::FileLeave,
            Permission::ViewOwnPayslips,
        ];

        $departmentSupervision = [
            Permission::ViewDepartmentEmployees,
            Permission::ViewDepartmentAttendance,
            Permission::EndorseLeave,
            Permission::ViewDepartmentReports,
        ];

        return match ($this) {
            self::SystemAdministrator => [
                Permission::ManageUsers,
                Permission::ManageSettings,
                Permission::ManageBackups,
                Permission::ManageBiometricDevices,
                Permission::ViewAuditLogs,
                Permission::ManageReferenceData,
            ],
            self::HrOfficer => [
                Permission::ManageReferenceData,
                Permission::ManageEmployees,
                Permission::ManageAttendance,
                Permission::ManageWorkCalendar,
                Permission::ApproveLeave,
                Permission::ManageLeaveCredits,
                Permission::ProcessPayroll,
                Permission::GenerateReports,
                Permission::ManageAnnouncements,
                ...$selfService,
            ],
            self::MunicipalMayor => [
                ...$departmentSupervision,
                Permission::EndorseDepartmentHeadLeave,
                ...$selfService,
            ],
            self::DepartmentHead => [...$departmentSupervision, ...$selfService],
            self::Employee => $selfService,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])
            ->all();
    }
}
