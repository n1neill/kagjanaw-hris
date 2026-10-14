<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An LGU office or department, e.g. the Municipal Treasurer's Office.
 */
#[Fillable(['code', 'name', 'description', 'is_active'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use Auditable, HasFactory;

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
