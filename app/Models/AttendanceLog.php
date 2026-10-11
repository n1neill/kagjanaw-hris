<?php

namespace App\Models;

use App\Enums\AttendanceLogStatus;
use App\Enums\AttendanceSource;
use App\Enums\VerifyType;
use Database\Factories\AttendanceLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A raw punch from a device or CSV import.
 */
#[Fillable(['biometric_device_id', 'employee_id', 'biometric_user_id', 'logged_at', 'verify_type', 'source', 'status'])]
class AttendanceLog extends Model
{
    /** @use HasFactory<AttendanceLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'verify_type' => VerifyType::class,
            'source' => AttendanceSource::class,
            'status' => AttendanceLogStatus::class,
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
     * @return BelongsTo<BiometricDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }
}
