<?php

namespace App\Http\Requests\Payroll;

use App\Enums\Permission;
use App\Models\AllowanceType;
use App\Models\EmployeeAllowance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assign an allowance to an employee, optionally at their own amount and
 * dates. Fields are prefixed because the page also has the deduction form.
 */
class EmployeeAllowanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ProcessPayroll);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'allowance_amount' => $this->filled('allowance_amount') ? $this->input('allowance_amount') : null,
            'allowance_starts_on' => $this->filled('allowance_starts_on') ? $this->input('allowance_starts_on') : null,
            'allowance_ends_on' => $this->filled('allowance_ends_on') ? $this->input('allowance_ends_on') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'allowance_type_id' => [
                'required', 'integer',
                Rule::exists(AllowanceType::class, 'id')->where('is_active', true),
                Rule::unique(EmployeeAllowance::class)->where('employee_id', $this->route('employee')->id),
            ],
            'allowance_amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:1000000'],
            'allowance_starts_on' => ['nullable', 'date'],
            'allowance_ends_on' => ['nullable', 'date', 'after_or_equal:allowance_starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allowance_type_id.unique' => __('This allowance is already assigned to the employee.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'allowance_amount' => __('monthly amount'),
            'allowance_starts_on' => __('start date'),
            'allowance_ends_on' => __('end date'),
        ];
    }

    /**
     * @return array{allowance_type_id: int, monthly_amount: ?string, starts_on: ?string, ends_on: ?string}
     */
    public function assignment(): array
    {
        return [
            'allowance_type_id' => (int) $this->validated('allowance_type_id'),
            'monthly_amount' => $this->validated('allowance_amount'),
            'starts_on' => $this->validated('allowance_starts_on'),
            'ends_on' => $this->validated('allowance_ends_on'),
        ];
    }
}
