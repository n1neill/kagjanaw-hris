<?php

namespace App\Http\Requests\Payroll;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The brackets of a monthly withholding tax table. Blank rows are ignored;
 * the first bracket must start at zero and lower bounds must differ.
 */
class TaxTableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ProcessPayroll);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'brackets' => collect($this->input('brackets', []))
                ->filter(fn ($bracket): bool => is_array($bracket) && collect($bracket)->only(['over', 'base_tax', 'rate'])->filter(fn ($value): bool => $value !== null && $value !== '')->isNotEmpty())
                ->values()
                ->all(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'legal_basis' => ['nullable', 'string', 'max:255'],
            'brackets' => ['required', 'array', 'min:1', 'max:15'],
            'brackets.*.over' => ['required', 'decimal:0,2', 'min:0', 'distinct'],
            'brackets.*.base_tax' => ['required', 'decimal:0,2', 'min:0'],
            'brackets.*.rate' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $lowest = collect($this->input('brackets'))->min(fn (array $bracket): float => (float) ($bracket['over'] ?? 0));

                if ($lowest !== null && $lowest > 0) {
                    $validator->errors()->add('brackets', __('The first bracket must start at 0.00.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'brackets.*.over' => __('lower bound'),
            'brackets.*.base_tax' => __('base tax'),
            'brackets.*.rate' => __('rate'),
        ];
    }
}
