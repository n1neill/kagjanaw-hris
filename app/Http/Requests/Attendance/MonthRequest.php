<?php

namespace App\Http\Requests\Attendance;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An optional "month" (YYYY-MM) for attendance pages, defaulting to the current month.
 * Authorization is checked by the controller against the employee being viewed.
 */
class MonthRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:'.now()->format('Y-m')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.before_or_equal' => __('Choose the current month or an earlier one.'),
        ];
    }

    /**
     * The first day of the requested month.
     */
    public function month(): CarbonImmutable
    {
        $month = $this->validated('month');

        return $month === null
            ? CarbonImmutable::now()->startOfMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
    }
}
