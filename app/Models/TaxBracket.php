<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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

    /**
     * The effectivity dates of the tax tables on file.
     *
     * @return Collection<int, string>
     */
    public static function effectivityDates(): Collection
    {
        return self::query()->select('effective_from')->distinct()->pluck('effective_from')
            ->map(fn ($date): string => $date->toDateString());
    }
}
