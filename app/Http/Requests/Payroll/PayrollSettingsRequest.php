<?php

namespace App\Http\Requests\Payroll;

use App\Enums\Permission;
use App\Services\Payroll\PayrollSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payroll settings: the LGU income class and its salary percentages, the
 * minimum net pay, the Job Order / COS tax rate and where absence deductions
 * start.
 */
class PayrollSettingsRequest extends FormRequest
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
            'income_class_confirmed' => $this->boolean('income_class_confirmed'),
            'attendance_deductions_from' => $this->filled('attendance_deductions_from') ? $this->input('attendance_deductions_from') : null,
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
            'income_class' => ['required', 'integer', Rule::in(array_keys(PayrollSettings::DEFAULT_PERCENTAGES))],
            'income_class_confirmed' => ['required', 'boolean'],
            'percentages' => ['required', 'array:'.implode(',', array_keys(PayrollSettings::DEFAULT_PERCENTAGES))],
            'percentages.*' => ['required', 'decimal:0,2', 'min:1', 'max:100'],
            'minimum_net_pay' => ['required', 'decimal:0,2', 'min:0', 'max:100000'],
            'daily_rate_tax_rate' => ['required', 'decimal:0,2', 'min:0', 'max:35'],
            'attendance_deductions_from' => ['nullable', 'date_format:Y-m', 'after_or_equal:2000-01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'percentages.*' => __('percentage'),
        ];
    }

    /**
     * @return array{income_class: int, income_class_confirmed: bool, percentages: array<int, string>, minimum_net_pay: string, daily_rate_tax_rate: string, attendance_deductions_from: ?string}
     */
    public function settings(): array
    {
        $percentages = [];

        foreach (array_keys(PayrollSettings::DEFAULT_PERCENTAGES) as $class) {
            $percentages[$class] = number_format((float) $this->validated('percentages')[$class], 2, '.', '');
        }

        return [
            'income_class' => (int) $this->validated('income_class'),
            'income_class_confirmed' => (bool) $this->validated('income_class_confirmed'),
            'percentages' => $percentages,
            'minimum_net_pay' => number_format((float) $this->validated('minimum_net_pay'), 2, '.', ''),
            'daily_rate_tax_rate' => number_format((float) $this->validated('daily_rate_tax_rate'), 2, '.', ''),
            'attendance_deductions_from' => $this->validated('attendance_deductions_from'),
        ];
    }
}
