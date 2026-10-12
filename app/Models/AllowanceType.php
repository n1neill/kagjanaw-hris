<?php

namespace App\Models;

use App\Enums\AllowanceCoverage;
use Database\Factories\AllowanceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An allowance HR defines, e.g. PERA. Paid half per semi-monthly payroll.
 */
#[Fillable(['code', 'name', 'description', 'monthly_amount', 'coverage', 'is_taxable', 'reduced_for_unpaid_days', 'is_active'])]
class AllowanceType extends Model
{
    /** @use HasFactory<AllowanceTypeFactory> */
    use HasFactory;

    public const PERA = 'PERA';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'coverage' => AllowanceCoverage::class,
            'is_taxable' => 'boolean',
            'reduced_for_unpaid_days' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EmployeeAllowance, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    /**
     * @param  Builder<AllowanceType>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
