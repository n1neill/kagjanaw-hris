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
    case ApproveLeave = 'leave.approve';
    case ProcessPayroll = 'payroll.process';
    case GenerateReports = 'reports.generate';
    case ManageAnnouncements = 'announcements.manage';

    // Department supervision
    case ViewDepartmentEmployees = 'employees.view-department';
    case ViewDepartmentAttendance = 'attendance.view-department';
    case EndorseLeave = 'leave.endorse';
    case ViewDepartmentReports = 'reports.view-department';

    // Employee self-service
    case ViewOwnRecords = 'records.view-own';
    case ViewOwnAttendance = 'attendance.view-own';
    case FileLeave = 'leave.file';
    case ViewOwnPayslips = 'payslips.view-own';
}
