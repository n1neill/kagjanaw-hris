<?php

namespace App\Enums;

/**
 * Granular abilities checked by route middleware, policies and Blade.
 *
 * Permissions for modules delivered in later phases are seeded now so that
 * role definitions stay stable as the system grows.
 */
enum Permission: string
{
    // System administration
    case ManageUsers = 'users.manage';
    case ManageSettings = 'settings.manage';
    case ManageBackups = 'backups.manage';
    case ManageBiometricDevices = 'biometric-devices.manage';
    case ViewAuditLogs = 'audit-logs.view';

    // HR administration
    case ManageReferenceData = 'reference-data.manage';
    case ManageEmployees = 'employees.manage';
    case ManageAttendance = 'attendance.manage';
    case ManageWorkCalendar = 'work-calendar.manage';
    case ApproveLeave = 'leave.approve';
    case ManageLeaveCredits = 'leave-credits.manage';
    case ProcessPayroll = 'payroll.process';
    case GenerateReports = 'reports.generate';
    case ManageAnnouncements = 'announcements.manage';
    case ApproveChanges = 'changes.approve';

    // Department supervision (EndorseDepartmentHeadLeave: the Municipal Mayor)
    case ViewDepartmentEmployees = 'employees.view-department';
    case ViewDepartmentAttendance = 'attendance.view-department';
    case EndorseLeave = 'leave.endorse';
    case EndorseDepartmentHeadLeave = 'leave.endorse-department-heads';
    case ViewDepartmentReports = 'reports.view-department';

    // Employee self-service
    case ViewOwnRecords = 'records.view-own';
    case ViewOwnAttendance = 'attendance.view-own';
    case FileLeave = 'leave.file';
    case ViewOwnPayslips = 'payslips.view-own';

    /**
     * HR administration permissions a System Administrator may grant to an
     * account on top of its role. Changes made through a granted permission
     * wait for an HR Officer's approval before they are saved.
     *
     * @return list<self>
     */
    public static function grantable(): array
    {
        return [
            self::ManageEmployees,
            self::ManageAttendance,
            self::ApproveLeave,
            self::ManageLeaveCredits,
            self::ProcessPayroll,
            self::ManageWorkCalendar,
            self::ManageReferenceData,
            self::ManageAnnouncements,
            self::GenerateReports,
        ];
    }

    public function isGrantable(): bool
    {
        return in_array($this, self::grantable(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ManageUsers => 'Manage user accounts',
            self::ManageSettings => 'Manage system settings',
            self::ManageBackups => 'Manage backups',
            self::ManageBiometricDevices => 'Manage biometric devices',
            self::ViewAuditLogs => 'View the audit log',
            self::ManageReferenceData => 'Manage reference data',
            self::ManageEmployees => 'Manage employees',
            self::ManageAttendance => 'Manage attendance',
            self::ManageWorkCalendar => 'Manage the work calendar',
            self::ApproveLeave => 'Approve leave applications',
            self::ManageLeaveCredits => 'Manage leave credits',
            self::ProcessPayroll => 'Process payroll',
            self::GenerateReports => 'Generate reports',
            self::ManageAnnouncements => 'Manage announcements',
            self::ApproveChanges => 'Approve submitted changes',
            self::ViewDepartmentEmployees => 'View department employees',
            self::ViewDepartmentAttendance => 'View department attendance',
            self::EndorseLeave => 'Endorse leave applications',
            self::EndorseDepartmentHeadLeave => 'Endorse department heads\' leave',
            self::ViewDepartmentReports => 'View department reports',
            self::ViewOwnRecords => 'View own records',
            self::ViewOwnAttendance => 'View own attendance',
            self::FileLeave => 'File leave applications',
            self::ViewOwnPayslips => 'View own payslips',
        };
    }

    /**
     * What a granted permission lets the account do, shown beside its checkbox.
     */
    public function description(): string
    {
        return match ($this) {
            self::ManageEmployees => 'Add and update employees, their 201 file documents and service records.',
            self::ManageAttendance => 'Correct daily time records, import punches and validate months.',
            self::ApproveLeave => 'Approve or reject endorsed leave applications.',
            self::ManageLeaveCredits => 'Post monthly credits, adjust balances and maintain leave types.',
            self::ProcessPayroll => 'Prepare and compute payroll runs and maintain payroll setup. Runs are approved and released by an HR Officer.',
            self::ManageWorkCalendar => 'Maintain holidays and office hours.',
            self::ManageReferenceData => 'Maintain departments, positions and employment types.',
            self::ManageAnnouncements => 'Post and edit announcements.',
            self::GenerateReports => 'View and export HR reports (read-only).',
            default => '',
        };
    }
}
