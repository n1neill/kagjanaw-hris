<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One bracket of the BIR monthly withholding tax table.
 */
#[Fillable(['effective_from', 'over', 'base_tax', 'rate', 'legal_basis'])]
class TaxBracket extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'over' => 'decimal:2',
            'base_tax' => 'decimal:2',
            'rate' => 'decimal:2',
        ];
    }
}
