<?php

namespace App\Http\Requests\ReferenceData;

use App\Enums\Permission;
use App\Models\EmploymentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmploymentTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageReferenceData);
    }

    /**
     * Normalize the code and treat an unchecked (unsubmitted) checkbox as false.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtolower(trim((string) $this->input('code'))),
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
        /** @var EmploymentType|null $employmentType */
        $employmentType = $this->route('employment_type');

        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique(EmploymentType::class)->ignore($employmentType)],
            'name' => ['required', 'string', 'max:255', Rule::unique(EmploymentType::class)->ignore($employmentType)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
