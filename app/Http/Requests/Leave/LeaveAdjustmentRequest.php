<?php

namespace App\Http\Requests\Leave;

use App\Enums\Permission;
use App\Models\LeaveType;
use App\Services\Leave\LeaveDays;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A manual correction to an employee's vacation or sick leave balance.
 */
class LeaveAdjustmentRequest extends FormRequest
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
            'ledger' => ['required', Rule::in([LeaveType::VACATION, LeaveType::SICK])],
            'days' => ['required', 'decimal:0,3', 'between:-999.999,999.999'],
            'entry_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('days') && LeaveDays::toThousandths($this->input('days')) === 0) {
                    $validator->errors()->add('days', __('Enter the number of days to add (positive) or deduct (negative).'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'days.decimal' => __('Enter days with up to three decimal places, e.g. 1.250 or -0.500.'),
        ];
    }

    public function ledgerType(): LeaveType
    {
        return $this->validated('ledger') === LeaveType::SICK ? LeaveType::sick() : LeaveType::vacation();
    }

    public function entryDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->validated('entry_date'));
    }
}
