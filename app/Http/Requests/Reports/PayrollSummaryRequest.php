<?php

namespace App\Http\Requests\Reports;

use App\Enums\PayBasis;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The period (a year, or one month of it) and payroll type for the payroll summary.
 */
class PayrollSummaryRequest extends FormRequest
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
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'pay_basis' => ['nullable', Rule::enum(PayBasis::class)],
        ];
    }

    public function year(): int
    {
        return (int) ($this->validated('year') ?? now()->year);
    }

    public function monthNumber(): ?int
    {
        $month = $this->validated('month');

        return $month === null ? null : (int) $month;
    }

    public function payBasis(): ?PayBasis
    {
        return PayBasis::tryFrom((string) $this->validated('pay_basis'));
    }
}
