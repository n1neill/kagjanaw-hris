<?php

namespace App\Http\Requests\Leave;

use App\Enums\LeaveCreditSource;
use App\Enums\Permission;
use App\Enums\Sex;
use App\Models\LeaveType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Leave type rules HR maintains when regulations change. The codes of the
 * types with leave card balances (VL, SL) and of forced leave are fixed.
 */
class LeaveTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageLeaveCredits);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->leaveType()?->exists && $this->isFixedType()
                ? $this->leaveType()->code
                : mb_strtoupper(trim((string) $this->input('code'))),
            'counts_calendar_days' => $this->boolean('counts_calendar_days'),
            'allows_half_day' => $this->boolean('allows_half_day'),
            'allows_past_dates' => $this->boolean('allows_past_dates'),
            'is_active' => $this->boolean('is_active'),
            'annual_days' => $this->filled('annual_days') ? $this->input('annual_days') : null,
            'max_days_per_request' => $this->filled('max_days_per_request') ? $this->input('max_days_per_request') : null,
            'eligible_sex' => $this->filled('eligible_sex') ? $this->input('eligible_sex') : null,
            'attachment_required_over_days' => $this->filled('attachment_required_over_days') ? $this->input('attachment_required_over_days') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->leaveType();
        $fixed = $this->isFixedType();

        return [
            'code' => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique(LeaveType::class)->ignore($type)],
            'name' => ['required', 'string', 'max:255', Rule::unique(LeaveType::class)->ignore($type)],
            'description' => ['nullable', 'string', 'max:2000'],
            'credit_source' => $fixed
                ? ['required', Rule::in([$type->credit_source->value])]
                : ['required', Rule::enum(LeaveCreditSource::class)],
            'annual_days' => ['nullable', 'decimal:0,3', 'min:0.5', 'max:366', Rule::requiredIf($this->input('credit_source') === LeaveCreditSource::AnnualAllotment->value)],
            'max_days_per_request' => ['nullable', 'decimal:0,3', 'min:0.5', 'max:366'],
            'counts_calendar_days' => ['required', 'boolean'],
            'allows_half_day' => ['required', 'boolean'],
            'advance_notice_days' => ['required', 'integer', 'between:0,60'],
            'allows_past_dates' => ['required', 'boolean'],
            'eligible_sex' => ['nullable', Rule::enum(Sex::class)],
            'attachment_required_over_days' => ['nullable', 'integer', 'between:0,366'],
            'attachment_hint' => ['nullable', 'string', 'max:255'],
            'is_active' => $fixed ? ['required', 'accepted'] : ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credit_source.in' => __('The credits this leave type is charged to cannot be changed.'),
            'is_active.accepted' => __('This leave type cannot be deactivated.'),
            'annual_days.required' => __('Enter the yearly allotment.'),
        ];
    }

    private function leaveType(): ?LeaveType
    {
        return $this->route('leaveType');
    }

    /**
     * Vacation, sick and forced leave drive the leave card and cannot be redefined.
     */
    private function isFixedType(): bool
    {
        return in_array($this->leaveType()?->code, [LeaveType::VACATION, LeaveType::SICK, LeaveType::FORCED], true);
    }
}
