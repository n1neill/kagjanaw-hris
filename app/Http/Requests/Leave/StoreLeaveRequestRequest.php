<?php

namespace App\Http\Requests\Leave;

use App\Enums\LeavePortion;
use App\Enums\Permission;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The form fields of an application for leave. The leave rules themselves
 * (advance notice, allotments, overlaps, etc.) are checked when filing by
 * App\Services\Leave\LeaveApplicationValidator.
 */
class StoreLeaveRequestRequest extends FormRequest
{
    /**
     * Largest accepted attachment, in kilobytes (5 MB).
     */
    public const MAX_ATTACHMENT_KILOBYTES = 5120;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::FileLeave) && $this->user()->employee !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('date_to') && $this->filled('date_from')) {
            $this->merge(['date_to' => $this->input('date_from')]);
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
            'leave_type_id' => ['required', 'integer', Rule::exists(LeaveType::class, 'id')->where('is_active', true)],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'portion' => ['required', Rule::enum(LeavePortion::class)],
            'details' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:'.self::MAX_ATTACHMENT_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => __('The last day must be on or after the first day.'),
            'attachment.mimes' => __('Attach a PDF, JPG or PNG file.'),
            'attachment.extensions' => __('Attach a PDF, JPG or PNG file.'),
            'attachment.max' => __('The attachment may not be larger than 5 MB.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'leave_type_id' => __('leave type'),
            'date_from' => __('first day'),
            'date_to' => __('last day'),
        ];
    }

    public function leaveType(): LeaveType
    {
        return LeaveType::findOrFail($this->validated('leave_type_id'));
    }

    public function dateFrom(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->validated('date_from'));
    }

    public function dateTo(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->validated('date_to'));
    }
}
