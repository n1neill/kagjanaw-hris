<?php

namespace App\Http\Requests\Employees;

use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Sex;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an employee record for both create and update.
 */
class EmployeeRequest extends FormRequest
{
    /**
     * Government ID fields, normalized to digits before validation.
     *
     * @var list<string>
     */
    private const GOVERNMENT_ID_FIELDS = ['gsis_number', 'philhealth_number', 'pagibig_number', 'tin'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $employee = $this->employee();

        return $employee === null
            ? $this->user()->can('create', Employee::class)
            : $this->user()->can('update', $employee);
    }

    /**
     * Strip the dashes and spaces people type into ID and phone numbers.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([...self::GOVERNMENT_ID_FIELDS, 'mobile_number', 'emergency_contact_number'] as $field) {
            if (filled($this->input($field))) {
                $normalized[$field] = preg_replace('/[\s\-().]/', '', (string) $this->input($field));
            }
        }

        if (filled($this->input('employee_number'))) {
            $normalized['employee_number'] = mb_strtoupper(trim((string) $this->input('employee_number')));
        }

        if (filled($this->input('email'))) {
            $normalized['email'] = mb_strtolower(trim((string) $this->input('email')));
        }

        $this->merge($normalized);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->employee();
        $linkedUserId = $employee?->user_id;
        $philippineMobile = 'regex:/^(09|\+639)\d{9}$/';

        return [
            'employee_number' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique(Employee::class)->ignore($employee),
                Rule::unique(User::class, 'username')->ignore($linkedUserId),
            ],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'name_extension' => ['nullable', 'string', 'max:10'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'civil_status' => ['required', Rule::enum(CivilStatus::class)],
            'birth_date' => ['required', 'date', 'before:-15 years', 'after:1900-01-01'],
            'birth_place' => ['nullable', 'string', 'max:255'],

            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($linkedUserId)],
            'mobile_number' => ['nullable', 'string', $philippineMobile],
            'residential_address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', $philippineMobile],

            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')],
            'position_id' => ['required', 'integer', Rule::exists(Position::class, 'id')],
            'employment_type_id' => ['required', 'integer', Rule::exists(EmploymentType::class, 'id')],
            'salary_grade' => ['nullable', 'integer', 'between:1,33', 'required_with:salary_step'],
            'salary_step' => ['nullable', 'integer', 'between:1,8', 'required_with:salary_grade'],
            'daily_rate' => ['nullable', 'decimal:0,2', 'min:0', 'max:100000'],
            'date_hired' => ['required', 'date', 'after:birth_date'],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'separation_date' => [
                Rule::requiredIf(fn (): bool => $this->input('employment_status') !== EmploymentStatus::Active->value),
                Rule::prohibitedIf(fn (): bool => $this->input('employment_status') === EmploymentStatus::Active->value),
                'nullable', 'date', 'after_or_equal:date_hired',
            ],

            'gsis_number' => ['nullable', 'digits_between:8,15'],
            'philhealth_number' => ['nullable', 'digits:12'],
            'pagibig_number' => ['nullable', 'digits:12'],
            'tin' => ['nullable', 'digits_between:9,12'],
            'biometric_user_id' => ['nullable', 'digits_between:1,9', Rule::unique(Employee::class)->ignore($employee)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_number.regex' => __('The employee ID may only contain letters, numbers and dashes.'),
            'employee_number.unique' => __('This employee ID is already in use.'),
            'birth_date.before' => __('The employee must be at least 15 years old.'),
            'mobile_number.regex' => __('Enter a Philippine mobile number, e.g. 09171234567.'),
            'emergency_contact_number.regex' => __('Enter a Philippine mobile number, e.g. 09171234567.'),
            'separation_date.required' => __('Enter the separation date for a separated employee.'),
            'separation_date.prohibited' => __('An active employee cannot have a separation date.'),
            'philhealth_number.digits' => __('The PhilHealth number must have 12 digits.'),
            'pagibig_number.digits' => __('The Pag-IBIG MID number must have 12 digits.'),
            'tin.digits_between' => __('The TIN must have 9 to 12 digits.'),
            'gsis_number.digits_between' => __('The GSIS number must have 8 to 15 digits.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'department_id' => 'department',
            'position_id' => 'position',
            'employment_type_id' => 'employment type',
            'biometric_user_id' => 'biometric user ID',
        ];
    }

    private function employee(): ?Employee
    {
        return $this->route('employee');
    }
}
