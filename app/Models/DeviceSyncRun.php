<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Database\Factories\DeviceSyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One synchronization attempt with a biometric device.
 */
#[Fillable([
    'biometric_device_id',
    'triggered_by',
    'status',
    'started_at',
    'finished_at',
    'fetched_count',
    'inserted_count',
    'duplicate_count',
    'flagged_count',
    'error_message',
])]
class DeviceSyncRun extends Model
{
    /** @use HasFactory<DeviceSyncRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'fetched_count' => 'integer',
            'inserted_count' => 'integer',
            'duplicate_count' => 'integer',
            'flagged_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BiometricDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
