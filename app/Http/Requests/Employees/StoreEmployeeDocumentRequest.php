<?php

namespace App\Http\Requests\Employees;

use App\Enums\DocumentType;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeDocumentRequest extends FormRequest
{
    /**
     * Largest accepted upload, in kilobytes (10 MB).
     */
    public const MAX_KILOBYTES = 10240;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return $this->user()->can('manageDocuments', $employee);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The mimes rule inspects the file contents, not just the extension, so a
     * renamed executable is rejected.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => __('Upload a PDF, JPG or PNG file.'),
            'file.extensions' => __('Upload a PDF, JPG or PNG file.'),
            'file.max' => __('The file may not be larger than 10 MB.'),
        ];
    }
}
