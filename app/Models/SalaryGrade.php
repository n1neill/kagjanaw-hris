<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One cell of the national monthly Salary Schedule: the amount for a grade
 * and step from a tranche's effectivity date.
 */
#[Fillable(['effective_from', 'grade', 'step', 'amount', 'legal_basis'])]
class SalaryGrade extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'grade' => 'integer',
            'step' => 'integer',
            'amount' => 'decimal:2',
        ];
    }
}
