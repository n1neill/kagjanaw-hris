<?php

namespace App\Http\Requests\WorkCalendar;

use App\Enums\HolidayType;
use App\Enums\Permission;
use App\Models\Holiday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HolidayRequest extends FormRequest
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
        /** @var Holiday|null $holiday */
        $holiday = $this->route('holiday');

        return [
            'date' => ['required', 'date_format:Y-m-d', Rule::unique(Holiday::class)->ignore($holiday)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(HolidayType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['date.unique' => __('There is already a holiday on this date.')];
    }
}
