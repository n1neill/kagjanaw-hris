<?php

namespace App\Http\Requests\Payroll;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The effectivity date of a new Salary Schedule tranche or tax table, which
 * starts as a copy of the one currently in force.
 */
class NewEffectivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ProcessPayroll);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date', 'after:2000-01-01'],
        ];
    }

    public function effectiveFrom(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('effective_from'))->startOfDay();
    }
}
