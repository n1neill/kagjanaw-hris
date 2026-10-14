<?php

namespace App\Http\Requests\Leave;

use App\Enums\Permission;
use App\Http\Requests\Concerns\ScopesReportToDepartment;
use App\Models\Department;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The period (a year, or one month of it) and office for the leave utilization
 * report. Department heads see only their own office.
 */
class LeaveUtilizationRequest extends FormRequest
{
    use ScopesReportToDepartment;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->seesAllDepartments() || $this->user()->can(Permission::ViewDepartmentReports);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'department' => ['nullable', 'integer', Rule::exists(Department::class, 'id')],
        ];
    }

    public function year(): int
    {
        return (int) ($this->validated('year') ?? now()->year);
    }

    public function monthNumber(): ?int
    {
        $month = $this->validated('month');

        return $month === null ? null : (int) $month;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(): array
    {
        $month = $this->monthNumber();

        $start = $month === null
            ? CarbonImmutable::create($this->year())->startOfYear()
            : CarbonImmutable::create($this->year(), $month)->startOfMonth();

        return [$start, $month === null ? $start->endOfYear() : $start->endOfMonth()];
    }

    public function seesAllDepartments(): bool
    {
        return $this->user()->can(Permission::ManageLeaveCredits) || $this->user()->can(Permission::GenerateReports);
    }
}
