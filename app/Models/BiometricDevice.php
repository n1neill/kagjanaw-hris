<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Database\Factories\BiometricDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A ZKTeco (or mock) time clock on the office LAN.
 */
#[Fillable(['name', 'driver', 'ip_address', 'port', 'location', 'is_active'])]
class BiometricDevice extends Model
{
    /** @use HasFactory<BiometricDeviceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'last_sync_status' => SyncStatus::class,
        ];
    }

    /**
     * @return HasMany<DeviceSyncRun, $this>
     */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(DeviceSyncRun::class);
    }

    /**
     * @return HasOne<DeviceSyncRun, $this>
     */
    public function lastSuccessfulRun(): HasOne
    {
        return $this->hasOne(DeviceSyncRun::class)->ofMany(
            ['started_at' => 'max'],
            fn ($query) => $query->where('status', SyncStatus::Succeeded),
        );
    }

    /**
     * @return HasMany<AttendanceLog, $this>
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
