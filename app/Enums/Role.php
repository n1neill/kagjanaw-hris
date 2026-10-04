<?php

namespace App\Enums;

/**
 * The four system roles. Case order is the precedence used to pick a
 * user's primary role when deciding which dashboard to show.
 */
enum Role: string
{
    case SystemAdministrator = 'system-administrator';
    case HrOfficer = 'hr-officer';
    case DepartmentHead = 'department-head';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'System Administrator',
            self::HrOfficer => 'HR Officer',
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
            self::DepartmentHead => 'dashboard.department-head',
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
                Permission::ApproveLeave,
                Permission::ProcessPayroll,
                Permission::GenerateReports,
                Permission::ManageAnnouncements,
                ...$selfService,
            ],
            self::DepartmentHead => [
                Permission::ViewDepartmentEmployees,
                Permission::ViewDepartmentAttendance,
                Permission::EndorseLeave,
                Permission::ViewDepartmentReports,
                ...$selfService,
            ],
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
