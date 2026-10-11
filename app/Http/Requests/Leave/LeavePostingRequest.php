<?php

namespace App\Http\Requests\Leave;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A finished month to post leave credits for, defaulting to last month.
 */
class LeavePostingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageLeaveCredits);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2000-01', 'before:'.now()->format('Y-m')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.before' => __('Leave credits can only be posted for a month that has ended.'),
        ];
    }

    public function month(): CarbonImmutable
    {
        $month = $this->validated('month');

        return $month === null
            ? CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth();
    }
}
