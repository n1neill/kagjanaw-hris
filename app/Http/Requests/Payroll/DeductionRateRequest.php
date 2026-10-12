<?php

namespace App\Http\Requests\Payroll;

use App\Enums\ContributionType;
use App\Enums\Permission;
use App\Models\DeductionRate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A GSIS, PhilHealth or Pag-IBIG contribution rate and its effectivity.
 */
class DeductionRateRequest extends FormRequest
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
            'salary_floor' => $this->filled('salary_floor') ? $this->input('salary_floor') : null,
            'salary_ceiling' => $this->filled('salary_ceiling') ? $this->input('salary_ceiling') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rate = $this->route('deductionRate');

        return [
            'type' => ['required', Rule::enum(ContributionType::class)],
            'effective_from' => [
                'required', 'date', 'after:1990-01-01',
                Rule::unique(DeductionRate::class)->where('type', $this->input('type'))->ignore($rate),
            ],
            'employee_rate' => ['required', 'decimal:0,2', 'min:0', 'max:50'],
            'employer_rate' => ['required', 'decimal:0,2', 'min:0', 'max:50'],
            'salary_floor' => ['nullable', 'decimal:0,2', 'min:0'],
            'salary_ceiling' => ['nullable', 'decimal:0,2', 'min:0', 'gte:salary_floor'],
            'legal_basis' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'effective_from.unique' => __('This contribution already has a rate effective on that date.'),
        ];
    }
}
