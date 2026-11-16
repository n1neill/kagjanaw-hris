<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\EmployeeServiceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One period of government service on an employee's CSC service record.
 * Maintained by HR; the employee can only view and print it.
 */
#[Fillable([
    'service_from',
    'service_to',
    'designation',
    'status',
    'annual_salary',
    'station',
    'branch',
    'leave_without_pay',
    'separation_date',
    'separation_cause',
    'remarks',
])]
class EmployeeServiceRecord extends Model
{
    /** @use HasFactory<EmployeeServiceRecordFactory> */
    use Auditable, HasFactory;

    /**
     * How the record is named in the audit log.
     */
    public function auditLabel(): string
    {
        return 'Service record entry ('.$this->designation.', '.$this->service_from?->format('Y-m-d').') of employee '.$this->employee?->employee_number;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_from' => 'date',
            'service_to' => 'date',
            'separation_date' => 'date',
            'annual_salary' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
