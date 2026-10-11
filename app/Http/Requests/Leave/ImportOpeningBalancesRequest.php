<?php

namespace App\Http\Requests\Leave;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportOpeningBalancesRequest extends FormRequest
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
            'as_of' => ['required', 'date_format:Y-m-d', 'before:today'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'extensions:csv', 'max:2048'],
        ];
    }

    /**
     * The cut-off must be a month end, so monthly credits start cleanly the next month.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('as_of')) {
                    return;
                }

                $asOf = CarbonImmutable::createFromFormat('!Y-m-d', $this->input('as_of'));

                if (! $asOf->isLastOfMonth()) {
                    $validator->errors()->add('as_of', __('Choose the last day of a month, e.g. :date.', ['date' => $asOf->endOfMonth()->format('M j, Y')]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'as_of.before' => __('The cut-off date must be in the past.'),
            'file.mimes' => __('Upload a CSV file.'),
            'file.extensions' => __('Upload a CSV file.'),
            'file.max' => __('The file may not be larger than 2 MB.'),
        ];
    }

    public function asOf(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->validated('as_of'));
    }
}
