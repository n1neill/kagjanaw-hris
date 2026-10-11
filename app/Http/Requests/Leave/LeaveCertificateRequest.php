<?php

namespace App\Http\Requests\Leave;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Options for the Certificate of Leave Credits.
 */
class LeaveCertificateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageLeaveCredits);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'as_of' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function asOf(): CarbonImmutable
    {
        $asOf = $this->validated('as_of');

        return $asOf === null ? CarbonImmutable::today() : CarbonImmutable::createFromFormat('!Y-m-d', $asOf);
    }
}
