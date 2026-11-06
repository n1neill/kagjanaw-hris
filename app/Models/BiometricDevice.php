<?php

namespace App\Models;

use App\Enums\SyncStatus;
use App\Models\Concerns\Auditable;
use App\Services\Attendance\BiometricDriverManager;
use Database\Factories\BiometricDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A time clock on the office LAN: a ZKTeco (or mock) device the HRIS pulls
 * punches from, or a DigitalPersona kiosk PC that pushes them to the HRIS.
 */
#[Fillable(['name', 'driver', 'ip_address', 'port', 'location', 'is_active'])]
#[Hidden(['api_token_hash'])]
class BiometricDevice extends Model
{
    /** @use HasFactory<BiometricDeviceFactory> */
    use Auditable, HasFactory;

    /**
     * Sync results are kept in the device's sync history instead.
     *
     * @return list<string>
     */
    public function auditExcluded(): array
    {
        return ['last_synced_at', 'last_sync_status', 'last_seen_at'];
    }

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
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Whether the device sends its punches to the HRIS (a kiosk) instead of
     * the HRIS reading them from the device.
     */
    public function receivesPushedPunches(): bool
    {
        return BiometricDriverManager::receivesPushedPunches($this->driver);
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
