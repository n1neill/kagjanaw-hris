<?php

namespace App\Models;

use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Sex;
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
    use HasFactory;

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
