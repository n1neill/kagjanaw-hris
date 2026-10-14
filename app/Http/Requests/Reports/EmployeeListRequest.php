<?php

namespace App\Http\Requests\Reports;

use App\Enums\EmploymentStatus;
use App\Enums\Permission;
use App\Http\Requests\Concerns\ScopesReportToDepartment;
use App\Models\Department;
use App\Models\EmploymentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the employee list report. Employees in service are listed
 * unless another status (or all) is chosen.
 */
class EmployeeListRequest extends FormRequest
{
    use ScopesReportToDepartment;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::GenerateReports) || $this->user()->can(Permission::ViewDepartmentReports);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department' => ['nullable', 'integer', Rule::exists(Department::class, 'id')],
            'employment_type' => ['nullable', 'integer', Rule::exists(EmploymentType::class, 'id')],
            'status' => ['nullable', Rule::in(['all', ...array_column(EmploymentStatus::cases(), 'value')])],
        ];
    }

    public function employmentTypeId(): ?int
    {
        $type = $this->validated('employment_type');

        return $type === null ? null : (int) $type;
    }

    /**
     * The status to list; null lists every status.
     */
    public function status(): ?EmploymentStatus
    {
        $status = $this->validated('status') ?? EmploymentStatus::Active->value;

        return $status === 'all' ? null : EmploymentStatus::from($status);
    }
}
