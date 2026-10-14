<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A plantilla position title with its default salary grade (SG 1-33).
 */
#[Fillable(['title', 'salary_grade', 'description', 'is_active'])]
class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary_grade' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
