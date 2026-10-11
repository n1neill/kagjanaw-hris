<?php

namespace App\Http\Requests\WorkCalendar;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WorkScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageWorkCalendar);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'am_start' => ['required', 'date_format:H:i'],
            'am_end' => ['required', 'date_format:H:i', 'after:am_start'],
            'pm_start' => ['required', 'date_format:H:i', 'after_or_equal:am_end'],
            'pm_end' => ['required', 'date_format:H:i', 'after:pm_start'],
            'grace_minutes' => ['required', 'integer', 'between:0,60'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'am_start' => 'morning start',
            'am_end' => 'morning end',
            'pm_start' => 'afternoon start',
            'pm_end' => 'afternoon end',
            'grace_minutes' => 'grace period',
            'working_days' => 'working days',
        ];
    }
}
