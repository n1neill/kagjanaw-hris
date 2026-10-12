<?php

namespace App\Http\Requests\Payroll;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A loan or other recurring deduction for one employee.
 */
class EmployeeDeductionRequest extends FormRequest
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
            'total_amount' => $this->filled('total_amount') ? $this->input('total_amount') : null,
            'ends_on' => $this->filled('ends_on') ? $this->input('ends_on') : null,
            'priority' => $this->filled('priority') ? $this->input('priority') : 100,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
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
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'monthly_amount' => ['required', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'total_amount' => ['nullable', 'decimal:0,2', 'min:0.01', 'max:100000000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'priority' => ['required', 'integer', 'between:1,999'],
            'is_active' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
