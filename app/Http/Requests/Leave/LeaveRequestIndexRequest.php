<?php

namespace App\Http\Requests\Leave;

use App\Enums\LeaveRequestStatus;
use App\Enums\Permission;
use App\Models\Department;
use App\Models\LeaveType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for HR's list of leave applications.
 */
class LeaveRequestIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ApproveLeave);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['all', ...array_column(LeaveRequestStatus::cases(), 'value')])],
            'department' => ['nullable', 'integer', Rule::exists(Department::class, 'id')],
            'type' => ['nullable', 'integer', Rule::exists(LeaveType::class, 'id')],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }

    /**
     * HR's work queue is the default: applications endorsed and awaiting approval.
     */
    public function status(): ?LeaveRequestStatus
    {
        $status = $this->validated('status') ?? LeaveRequestStatus::Endorsed->value;

        return $status === 'all' ? null : LeaveRequestStatus::from($status);
    }

    public function year(): int
    {
        return (int) ($this->validated('year') ?? now()->year);
    }
}
