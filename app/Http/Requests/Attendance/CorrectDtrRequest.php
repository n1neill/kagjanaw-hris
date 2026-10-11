<?php

namespace App\Http\Requests\Attendance;

use App\Models\Employee;
use App\Services\Attendance\DtrCorrectionService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Corrected punches for one DTR day and the reason for the correction.
 */
class CorrectDtrRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageAttendance', $this->employee());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $time = ['nullable', 'date_format:H:i'];

        return [
            'am_in' => $time,
            'am_out' => $time,
            'pm_in' => $time,
            'pm_out' => $time,
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * Punches must be in time order, and something must actually change.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(DtrCorrectionService::PUNCH_FIELDS)) {
                    return;
                }

                $previousField = null;

                foreach (DtrCorrectionService::PUNCH_FIELDS as $field) {
                    $value = $this->input($field);

                    if (blank($value)) {
                        continue;
                    }

                    if ($previousField !== null && $value <= $this->input($previousField)) {
                        $validator->errors()->add($field, __('This punch must be later than the one before it.'));

                        return;
                    }

                    $previousField = $field;
                }

                $service = app(DtrCorrectionService::class);
                $current = $service->punchTimes($service->recordFor($this->employee(), $this->workDate()));

                $unchanged = collect(DtrCorrectionService::PUNCH_FIELDS)
                    ->every(fn (string $field): bool => $current[$field] === ($this->input($field) ?: null));

                if ($unchanged) {
                    $validator->errors()->add('am_in', __('No punches were changed.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'am_in' => 'morning arrival',
            'am_out' => 'morning departure',
            'pm_in' => 'afternoon arrival',
            'pm_out' => 'afternoon departure',
        ];
    }

    /**
     * @return array{am_in: ?string, am_out: ?string, pm_in: ?string, pm_out: ?string}
     */
    public function punches(): array
    {
        $validated = $this->validated();

        return [
            'am_in' => $validated['am_in'] ?? null,
            'am_out' => $validated['am_out'] ?? null,
            'pm_in' => $validated['pm_in'] ?? null,
            'pm_out' => $validated['pm_out'] ?? null,
        ];
    }

    public function employee(): Employee
    {
        return $this->route('employee');
    }

    public function workDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->route('date'));
    }
}
