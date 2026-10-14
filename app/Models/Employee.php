<?php

namespace App\Models;

use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\PayBasis;
use App\Enums\Sex;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An LGU employee and their 201 file. Government ID numbers are encrypted at
 * rest and hidden from serialization (RA 10173, Data Privacy Act).
 */
#[Fillable([
    'employee_number',
    'last_name',
    'first_name',
    'middle_name',
    'name_extension',
    'sex',
    'civil_status',
    'birth_date',
    'birth_place',
    'mobile_number',
    'residential_address',
    'emergency_contact_name',
    'emergency_contact_number',
    'department_id',
    'position_id',
    'employment_type_id',
    'salary_grade',
    'salary_step',
    'daily_rate',
    'date_hired',
    'employment_status',
    'separation_date',
    'gsis_number',
    'philhealth_number',
    'pagibig_number',
    'tin',
    'biometric_user_id',
])]
#[Hidden(['gsis_number', 'philhealth_number', 'pagibig_number', 'tin'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, HasFactory;

    /**
     * How the record is named in the audit log.
     */
    public function auditLabel(): string
    {
        return $this->sortable_name.' ('.$this->employee_number.')';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'civil_status' => CivilStatus::class,
            'birth_date' => 'date',
            'salary_grade' => 'integer',
            'salary_step' => 'integer',
            'daily_rate' => 'decimal:2',
            'date_hired' => 'date',
            'employment_status' => EmploymentStatus::class,
            'separation_date' => 'date',
            'gsis_number' => 'encrypted',
            'philhealth_number' => 'encrypted',
            'pagibig_number' => 'encrypted',
            'tin' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * @return HasMany<DailyTimeRecord, $this>
     */
    public function dailyTimeRecords(): HasMany
    {
        return $this->hasMany(DailyTimeRecord::class);
    }

    /**
     * @return HasMany<AttendanceLog, $this>
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<LeaveRequestDay, $this>
     */
    public function leaveRequestDays(): HasMany
    {
        return $this->hasMany(LeaveRequestDay::class);
    }

    /**
     * @return HasMany<LeaveBalance, $this>
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * @return HasMany<LeaveLedgerEntry, $this>
     */
    public function leaveLedgerEntries(): HasMany
    {
        return $this->hasMany(LeaveLedgerEntry::class);
    }

    /**
     * @return HasMany<EmployeeAllowance, $this>
     */
    public function allowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    /**
     * @return HasMany<EmployeeDeduction, $this>
     */
    public function payrollDeductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * How the employee is paid, from their employment type.
     */
    public function payBasis(): PayBasis
    {
        return $this->employmentType?->pay_basis ?? PayBasis::SalaryGrade;
    }

    /**
     * Whether the employee earns vacation and sick leave credits (not elective
     * officials or Job Order / Contract of Service workers).
     */
    public function earnsLeaveCredits(): bool
    {
        return (bool) $this->employmentType?->earns_leave_credits;
    }

    /**
     * Whether the employee was in service on the given date.
     */
    public function wasEmployedOn(CarbonInterface $date): bool
    {
        return $date->greaterThanOrEqualTo($this->date_hired)
            && ($this->separation_date === null || $date->lessThanOrEqualTo($this->separation_date));
    }

    /**
     * Name in reading order, e.g. "Juan A. Dela Cruz Jr.".
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([
            $this->first_name,
            $this->middle_name ? mb_substr($this->middle_name, 0, 1).'.' : null,
            $this->last_name,
            $this->name_extension,
        ])->filter()->implode(' '));
    }

    /**
     * Name in list order, e.g. "Dela Cruz, Juan A. Jr.".
     *
     * @return Attribute<string, never>
     */
    protected function sortableName(): Attribute
    {
        return Attribute::get(fn (): string => $this->last_name.', '.collect([
            $this->first_name,
            $this->middle_name ? mb_substr($this->middle_name, 0, 1).'.' : null,
            $this->name_extension,
        ])->filter()->implode(' '));
    }

    /**
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function inService(Builder $query): void
    {
        $query->where('employment_status', EmploymentStatus::Active);
    }
}
