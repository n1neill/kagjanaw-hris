<?php

namespace App\Http\Requests\Employees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One period of service on an employee's service record. When a separation
 * date is given, the period ends on that date.
 */
class ServiceRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageServiceRecord', $this->route('employee'));
    }

    protected function prepareForValidation(): void
    {
        $optional = ['service_to', 'annual_salary', 'station', 'leave_without_pay', 'separation_date', 'separation_cause', 'remarks'];

        $this->merge(collect($optional)
            ->mapWithKeys(fn (string $field): array => [$field => $this->filled($field) ? $this->input($field) : null])
            ->put('branch', $this->filled('branch') ? $this->input('branch') : 'Local')
            ->all());

        // Service ends on the day of separation.
        if ($this->filled('separation_date')) {
            $this->merge(['service_to' => $this->input('separation_date')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_from' => ['required', 'date'],
            'service_to' => ['nullable', 'date', 'after_or_equal:service_from'],
            'designation' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:100'],
            'annual_salary' => ['nullable', 'decimal:0,2', 'min:0', 'max:100000000'],
            'station' => ['nullable', 'string', 'max:255'],
            'branch' => ['required', 'string', 'max:100'],
            'leave_without_pay' => ['nullable', 'string', 'max:100'],
            'separation_date' => ['nullable', 'date', 'after_or_equal:service_from'],
            'separation_cause' => ['nullable', 'required_with:separation_date', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_to.after_or_equal' => __('The end of service cannot be before its start.'),
            'separation_date.after_or_equal' => __('The separation date cannot be before the start of service.'),
            'separation_cause.required_with' => __('Give the cause of separation.'),
        ];
    }
}
