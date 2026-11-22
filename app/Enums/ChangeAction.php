<?php

namespace App\Enums;

use App\Services\Approvals\Handlers\AnnouncementChanges;
use App\Services\Approvals\Handlers\AttendanceChanges;
use App\Services\Approvals\Handlers\EmployeeChanges;
use App\Services\Approvals\Handlers\LeaveChanges;
use App\Services\Approvals\Handlers\PayrollChanges;
use App\Services\Approvals\Handlers\ReferenceDataChanges;
use App\Services\Approvals\Handlers\WorkCalendarChanges;
use Illuminate\Support\Str;

/**
 * Every change to HR records that goes through ChangeRequestService. A user
 * holding the action's permission through their role applies it at once; a
 * user holding it only as a direct grant submits it for HR Officer approval.
 *
 * Each case is applied by the handler class's method named after the case
 * (e.g. CreateEmployee → EmployeeChanges::createEmployee()).
 */
enum ChangeAction: string
{
    // Employees
    case CreateEmployee = 'employee.create';
    case UpdateEmployee = 'employee.update';
    case AddServiceRecord = 'service-record.create';
    case UpdateServiceRecord = 'service-record.update';
    case DeleteServiceRecord = 'service-record.delete';
    case UploadEmployeeDocument = 'employee-document.create';
    case DeleteEmployeeDocument = 'employee-document.delete';
    case RemoveFingerprint = 'fingerprint.delete';

    // Attendance
    case CorrectDtr = 'dtr.correct';
    case ValidateDtrMonth = 'dtr.validate-month';
    case ReprocessDtrMonth = 'dtr.reprocess-month';
    case ImportAttendance = 'attendance.import';
    case ClearAttendanceLogs = 'attendance-logs.clear';

    // Leave applications
    case ApproveLeaveRequest = 'leave-request.approve';
    case RejectLeaveRequest = 'leave-request.reject';
    case CancelLeaveRequest = 'leave-request.cancel';

    // Leave credits
    case CreateLeaveType = 'leave-type.create';
    case UpdateLeaveType = 'leave-type.update';
    case AdjustLeaveBalance = 'leave-balance.adjust';
    case PostLeaveCredits = 'leave-credits.post';
    case ImportOpeningBalances = 'leave-balance.import';

    // Work calendar
    case CreateHoliday = 'holiday.create';
    case UpdateHoliday = 'holiday.update';
    case DeleteHoliday = 'holiday.delete';
    case UpdateOfficeHours = 'office-hours.update';

    // Reference data
    case CreateDepartment = 'department.create';
    case UpdateDepartment = 'department.update';
    case CreatePosition = 'position.create';
    case UpdatePosition = 'position.update';
    case CreateEmploymentType = 'employment-type.create';
    case UpdateEmploymentType = 'employment-type.update';

    // Payroll
    case DeletePayrollRun = 'payroll-run.delete';
    case CreateAllowanceType = 'allowance-type.create';
    case UpdateAllowanceType = 'allowance-type.update';
    case AssignAllowance = 'employee-allowance.create';
    case RemoveAllowance = 'employee-allowance.delete';
    case AddDeduction = 'employee-deduction.create';
    case UpdateDeduction = 'employee-deduction.update';
    case CreateContributionRate = 'contribution-rate.create';
    case UpdateContributionRate = 'contribution-rate.update';
    case CreateTaxTable = 'tax-table.create';
    case UpdateTaxTable = 'tax-table.update';
    case DeleteTaxTable = 'tax-table.delete';
    case CreateSalaryTranche = 'salary-tranche.create';
    case UpdateSalaryTranche = 'salary-tranche.update';
    case UpdatePayrollSettings = 'payroll-settings.update';

    // Announcements
    case CreateAnnouncement = 'announcement.create';
    case UpdateAnnouncement = 'announcement.update';
    case DeleteAnnouncement = 'announcement.delete';

    /**
     * The permission whose grant makes this action wait for approval.
     */
    public function permission(): Permission
    {
        return match ($this) {
            self::CreateEmployee, self::UpdateEmployee, self::AddServiceRecord, self::UpdateServiceRecord,
            self::DeleteServiceRecord, self::UploadEmployeeDocument, self::DeleteEmployeeDocument,
            self::RemoveFingerprint => Permission::ManageEmployees,

            self::CorrectDtr, self::ValidateDtrMonth, self::ReprocessDtrMonth, self::ImportAttendance,
            self::ClearAttendanceLogs => Permission::ManageAttendance,

            self::ApproveLeaveRequest, self::RejectLeaveRequest, self::CancelLeaveRequest => Permission::ApproveLeave,

            self::CreateLeaveType, self::UpdateLeaveType, self::AdjustLeaveBalance, self::PostLeaveCredits,
            self::ImportOpeningBalances => Permission::ManageLeaveCredits,

            self::CreateHoliday, self::UpdateHoliday, self::DeleteHoliday, self::UpdateOfficeHours => Permission::ManageWorkCalendar,

            self::CreateDepartment, self::UpdateDepartment, self::CreatePosition, self::UpdatePosition,
            self::CreateEmploymentType, self::UpdateEmploymentType => Permission::ManageReferenceData,

            self::DeletePayrollRun, self::CreateAllowanceType, self::UpdateAllowanceType, self::AssignAllowance,
            self::RemoveAllowance, self::AddDeduction, self::UpdateDeduction, self::CreateContributionRate,
            self::UpdateContributionRate, self::CreateTaxTable, self::UpdateTaxTable, self::DeleteTaxTable,
            self::CreateSalaryTranche, self::UpdateSalaryTranche, self::UpdatePayrollSettings => Permission::ProcessPayroll,

            self::CreateAnnouncement, self::UpdateAnnouncement, self::DeleteAnnouncement => Permission::ManageAnnouncements,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CreateEmployee => 'Add employee',
            self::UpdateEmployee => 'Update employee record',
            self::AddServiceRecord => 'Add service record entry',
            self::UpdateServiceRecord => 'Update service record entry',
            self::DeleteServiceRecord => 'Delete service record entry',
            self::UploadEmployeeDocument => 'Upload 201 file document',
            self::DeleteEmployeeDocument => 'Delete 201 file document',
            self::RemoveFingerprint => 'Remove enrolled fingerprint',
            self::CorrectDtr => 'Correct DTR',
            self::ValidateDtrMonth => 'Validate DTR month',
            self::ReprocessDtrMonth => 'Recompute DTR month',
            self::ImportAttendance => 'Import attendance punches',
            self::ClearAttendanceLogs => 'Clear attendance logs',
            self::ApproveLeaveRequest => 'Approve leave application',
            self::RejectLeaveRequest => 'Reject leave application',
            self::CancelLeaveRequest => 'Cancel approved leave',
            self::CreateLeaveType => 'Add leave type',
            self::UpdateLeaveType => 'Update leave type',
            self::AdjustLeaveBalance => 'Adjust leave balance',
            self::PostLeaveCredits => 'Post monthly leave credits',
            self::ImportOpeningBalances => 'Import opening leave balances',
            self::CreateHoliday => 'Add holiday',
            self::UpdateHoliday => 'Update holiday',
            self::DeleteHoliday => 'Remove holiday',
            self::UpdateOfficeHours => 'Update office hours',
            self::CreateDepartment => 'Add department',
            self::UpdateDepartment => 'Update department',
            self::CreatePosition => 'Add position',
            self::UpdatePosition => 'Update position',
            self::CreateEmploymentType => 'Add employment type',
            self::UpdateEmploymentType => 'Update employment type',
            self::DeletePayrollRun => 'Delete payroll run',
            self::CreateAllowanceType => 'Add allowance type',
            self::UpdateAllowanceType => 'Update allowance type',
            self::AssignAllowance => 'Assign allowance',
            self::RemoveAllowance => 'Remove allowance',
            self::AddDeduction => 'Add deduction',
            self::UpdateDeduction => 'Update deduction',
            self::CreateContributionRate => 'Add contribution rate',
            self::UpdateContributionRate => 'Update contribution rate',
            self::CreateTaxTable => 'Add withholding tax table',
            self::UpdateTaxTable => 'Update withholding tax table',
            self::DeleteTaxTable => 'Remove withholding tax table',
            self::CreateSalaryTranche => 'Add Salary Schedule tranche',
            self::UpdateSalaryTranche => 'Update Salary Schedule tranche',
            self::UpdatePayrollSettings => 'Update payroll settings',
            self::CreateAnnouncement => 'Post announcement',
            self::UpdateAnnouncement => 'Update announcement',
            self::DeleteAnnouncement => 'Delete announcement',
        };
    }

    /**
     * @return class-string
     */
    public function handler(): string
    {
        return match ($this->permission()) {
            Permission::ManageEmployees => EmployeeChanges::class,
            Permission::ManageAttendance => AttendanceChanges::class,
            Permission::ApproveLeave, Permission::ManageLeaveCredits => LeaveChanges::class,
            Permission::ManageWorkCalendar => WorkCalendarChanges::class,
            Permission::ManageReferenceData => ReferenceDataChanges::class,
            Permission::ProcessPayroll => PayrollChanges::class,
            Permission::ManageAnnouncements => AnnouncementChanges::class,
        };
    }

    /**
     * The handler method that applies this action.
     */
    public function method(): string
    {
        return Str::camel($this->name);
    }

    /**
     * Whether the change replaces an existing record's values, so the review
     * screen compares the current values with the proposed ones.
     */
    public function isUpdate(): bool
    {
        return str_starts_with($this->name, 'Update');
    }
}
