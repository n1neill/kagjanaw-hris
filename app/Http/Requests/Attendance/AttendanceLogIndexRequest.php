<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceLogStatus;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the raw attendance log.
 */
class AttendanceLogIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageAttendance);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(AttendanceLogStatus::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'biometric_user_id' => ['nullable', 'digits_between:1,9'],
        ];
    }
}
