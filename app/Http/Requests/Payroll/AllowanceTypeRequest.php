<?php

namespace App\Http\Requests\Payroll;

use App\Enums\AllowanceCoverage;
use App\Enums\Permission;
use App\Models\AllowanceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An allowance HR defines: amount, who receives it and how it is treated.
 */
class AllowanceTypeRequest extends FormRequest
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
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'is_taxable' => $this->boolean('is_taxable'),
            'reduced_for_unpaid_days' => $this->boolean('reduced_for_unpaid_days'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('allowanceType');

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique(AllowanceType::class)->ignore($type)],
            'name' => ['required', 'string', 'max:255', Rule::unique(AllowanceType::class)->ignore($type)],
            'description' => ['nullable', 'string', 'max:2000'],
            'monthly_amount' => ['required', 'decimal:0,2', 'min:0', 'max:1000000'],
            'coverage' => ['required', Rule::enum(AllowanceCoverage::class)],
            'is_taxable' => ['required', 'boolean'],
            'reduced_for_unpaid_days' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
