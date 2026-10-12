<?php

namespace App\Models;

use App\Enums\ContributionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A GSIS, PhilHealth or Pag-IBIG contribution rate from its effectivity date.
 */
#[Fillable(['type', 'effective_from', 'employee_rate', 'employer_rate', 'salary_floor', 'salary_ceiling', 'legal_basis'])]
class DeductionRate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContributionType::class,
            'effective_from' => 'date',
            'employee_rate' => 'decimal:2',
            'employer_rate' => 'decimal:2',
            'salary_floor' => 'decimal:2',
            'salary_ceiling' => 'decimal:2',
        ];
    }
}
