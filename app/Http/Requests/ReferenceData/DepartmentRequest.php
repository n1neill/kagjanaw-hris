<?php

namespace App\Http\Requests\ReferenceData;

use App\Enums\Permission;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
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
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
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
        /** @var Department|null $department */
        $department = $this->route('department');

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique(Department::class)->ignore($department)],
            'name' => ['required', 'string', 'max:255', Rule::unique(Department::class)->ignore($department)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
