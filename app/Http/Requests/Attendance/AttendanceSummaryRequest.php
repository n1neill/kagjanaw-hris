<?php

namespace App\Http\Requests\Attendance;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Month and (for HR) department filters for the attendance summary.
 */
class AttendanceSummaryRequest extends MonthRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageAttendance) || $this->user()->can(Permission::ViewDepartmentAttendance);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'department' => ['nullable', 'integer'],
        ];
    }
}
