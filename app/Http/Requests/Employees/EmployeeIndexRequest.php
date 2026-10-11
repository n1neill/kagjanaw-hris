<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Optional filters for the employee list.
 */
class EmployeeIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Employee::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['all', ...array_column(EmploymentStatus::cases(), 'value')])],
        ];
    }

    /**
     * The status filter defaults to employees currently in service.
     */
    public function status(): ?EmploymentStatus
    {
        $status = $this->validated('status') ?? EmploymentStatus::Active->value;

        return $status === 'all' ? null : EmploymentStatus::from($status);
    }
}
