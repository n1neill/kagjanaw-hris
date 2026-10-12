<?php

namespace App\Http\Requests\Payroll;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The amounts of one Salary Schedule tranche: amounts[grade][step], in pesos.
 */
class SalaryTrancheRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ProcessPayroll);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'legal_basis' => ['nullable', 'string', 'max:255'],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['array'],
            'amounts.*.*' => ['nullable', 'decimal:0,2', 'min:1', 'max:9999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amounts.*.*' => __('amount'),
        ];
    }
}
