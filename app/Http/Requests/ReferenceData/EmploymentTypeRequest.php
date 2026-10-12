<?php

namespace App\Http\Requests\ReferenceData;

use App\Enums\PayBasis;
use App\Enums\Permission;
use App\Models\EmploymentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmploymentTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageReferenceData);
    }

    /**
     * Normalize the code, treat an unchecked (unsubmitted) checkbox as false
     * and keep the current pay basis (or salary grade) when none is sent.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtolower(trim((string) $this->input('code'))),
            'earns_leave_credits' => $this->boolean('earns_leave_credits'),
            'pay_basis' => $this->input('pay_basis', $this->route('employment_type')?->pay_basis?->value ?? PayBasis::SalaryGrade->value),
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
        /** @var EmploymentType|null $employmentType */
        $employmentType = $this->route('employment_type');

        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique(EmploymentType::class)->ignore($employmentType)],
            'name' => ['required', 'string', 'max:255', Rule::unique(EmploymentType::class)->ignore($employmentType)],
            'description' => ['nullable', 'string', 'max:1000'],
            'earns_leave_credits' => ['required', 'boolean'],
            'pay_basis' => ['required', Rule::enum(PayBasis::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
