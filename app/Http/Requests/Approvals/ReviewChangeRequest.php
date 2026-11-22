<?php

namespace App\Http\Requests\Approvals;

use App\Models\ChangeRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve or reject a submitted change, with remarks. Remarks are required
 * when rejecting, so the submitter knows why.
 */
class ReviewChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->changeRequest());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'remarks' => [$this->isRejection() ? 'required' : 'nullable', 'string', 'max:1000'],
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

    public function changeRequest(): ChangeRequest
    {
        return $this->route('changeRequest');
    }

    private function isRejection(): bool
    {
        return str_ends_with((string) $this->route()?->getName(), '.reject');
    }
}
