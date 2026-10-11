<?php

namespace App\Http\Requests\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Endorse, approve, reject or cancel a leave application, with remarks.
 * Remarks are required when rejecting, and when HR cancels approved leave.
 */
class LeaveRemarksRequest extends FormRequest
{
    /**
     * Route action → policy ability.
     *
     * @var array<string, string>
     */
    private const ABILITIES = [
        'endorse' => 'endorse',
        'approve' => 'decide',
        'reject' => 'reject',
        'cancel' => 'cancel',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(self::ABILITIES[$this->action()], $this->leaveRequest());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->action() === 'reject'
            || ($this->action() === 'cancel' && $this->leaveRequest()->status === LeaveRequestStatus::Approved);

        return [
            'remarks' => [$required ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'remarks.required' => __('Give the reason in the remarks.'),
        ];
    }

    public function action(): string
    {
        return (string) str($this->route()->getName())->afterLast('.');
    }

    public function leaveRequest(): LeaveRequest
    {
        return $this->route('leaveRequest');
    }
}
