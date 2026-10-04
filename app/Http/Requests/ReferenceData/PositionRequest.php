<?php

namespace App\Http\Requests\ReferenceData;

use App\Enums\Permission;
use App\Models\Position;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageReferenceData);
    }

    /**
     * Treat an unchecked (unsubmitted) checkbox as false.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Position|null $position */
        $position = $this->route('position');

        return [
            'title' => ['required', 'string', 'max:255', Rule::unique(Position::class)->ignore($position)],
            'salary_grade' => ['nullable', 'integer', 'between:1,33'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
