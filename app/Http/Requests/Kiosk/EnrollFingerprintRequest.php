<?php

namespace App\Http\Requests\Kiosk;

use App\Enums\Finger;
use App\Models\Employee;
use App\Services\Attendance\Kiosk\KioskEnrollmentSessions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One finger enrolled at the kiosk: the matcher's template (base64). The
 * kiosk has already checked that the finger matches no other employee.
 */
class EnrollFingerprintRequest extends FormRequest
{
    /**
     * Base64 of a SourceAFIS template; real ones are a few kilobytes.
     */
    private const MAX_TEMPLATE_LENGTH = 24000;

    public function authorize(): bool
    {
        return KioskEnrollmentSessions::mayEnroll($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', 'string', 'max:'.self::MAX_TEMPLATE_LENGTH, 'regex:/^[A-Za-z0-9+\/]+={0,2}$/'],
            'quality' => ['nullable', 'integer', 'between:0,100'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Employee $employee */
                $employee = $this->route('employee');
                /** @var Finger $finger */
                $finger = $this->route('finger');
                $maxFingers = (int) config('attendance.kiosk.max_fingers_per_employee');

                if (! $employee->employment_status->isInService() || blank($employee->biometric_user_id)) {
                    $validator->errors()->add('employee', __(':name is not in service or has no biometric user ID in the HRIS.', ['name' => $employee->full_name]));

                    return;
                }

                $otherFingers = $employee->fingerprintTemplates()->where('finger', '!=', $finger)->count();

                if ($otherFingers >= $maxFingers) {
                    $validator->errors()->add('finger', __(':name already has :count fingers enrolled. Remove one in the HRIS first.', [
                        'name' => $employee->full_name,
                        'count' => $maxFingers,
                    ]));
                }
            },
        ];
    }
}
