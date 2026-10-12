<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PayBasis;
use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new payroll run: the month, which half, and which payroll.
 */
class StorePayrollRunRequest extends FormRequest
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
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:'.now()->addYear()->format('Y-m')],
            'half' => ['required', 'integer', Rule::in([1, 2])],
            'pay_basis' => ['required', Rule::enum(PayBasis::class)],
        ];
    }

    public function month(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->validated('month'));
    }

    public function half(): int
    {
        return (int) $this->validated('half');
    }

    public function payBasis(): PayBasis
    {
        return PayBasis::from($this->validated('pay_basis'));
    }
}
