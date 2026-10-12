<?php

namespace App\Models;

use App\Enums\PayBasis;
use Database\Factories\EmploymentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Nature of appointment, e.g. Permanent, Casual, Job Order.
 */
#[Fillable(['code', 'name', 'description', 'earns_leave_credits', 'pay_basis', 'is_active'])]
class EmploymentType extends Model
{
    /** @use HasFactory<EmploymentTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'earns_leave_credits' => 'boolean',
            'pay_basis' => PayBasis::class,
            'is_active' => 'boolean',
        ];
    }
}
