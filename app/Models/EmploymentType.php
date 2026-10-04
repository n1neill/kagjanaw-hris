<?php

namespace App\Models;

use Database\Factories\EmploymentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Nature of appointment, e.g. Permanent, Casual, Job Order.
 */
#[Fillable(['code', 'name', 'description', 'is_active'])]
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
            'is_active' => 'boolean',
        ];
    }
}
