<?php

namespace App\Services\Attendance\Kiosk;

use App\Enums\Finger;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use App\Models\User;
use App\Services\Attendance\AttendanceSyncService;
use Illuminate\Support\Facades\DB;

/**
 * Stores and removes enrolled fingerprints. Each change is audited through
 * the model (the template itself is never written to the audit log).
 */
class FingerprintEnrollmentService
{
    public function __construct(private AttendanceSyncService $attendance) {}

    /**
     * Enroll a finger, replacing an earlier enrollment of the same finger.
     */
    public function enroll(Employee $employee, Finger $finger, string $template, ?int $quality, User $enrolledBy, ?BiometricDevice $device = null): FingerprintTemplate
    {
        $this->ensureBiometricUserId($employee);

        $fingerprint = $employee->fingerprintTemplates()->firstOrNew(['finger' => $finger]);
        $fingerprint->setRelation('employee', $employee);

        $fingerprint->fill([
            'template' => $template,
            'quality' => $quality,
            'enrolled_by' => $enrolledBy->id,
            'biometric_device_id' => $device?->id,
        ])->save();

        return $fingerprint;
    }

    /**
     * Give an employee without a biometric user ID the next free numeric one,
     * so newly added employees can be enrolled right away at the kiosk.
     */
    private function ensureBiometricUserId(Employee $employee): void
    {
        if (filled($employee->biometric_user_id)) {
            return;
        }

        DB::transaction(function () use ($employee): void {
            $highest = (int) Employee::query()
                ->lockForUpdate()
                ->whereRaw('biometric_user_id REGEXP ?', ['^[0-9]{1,8}$'])
                ->max(DB::raw('CAST(biometric_user_id AS UNSIGNED)'));

            $employee->update(['biometric_user_id' => (string) ($highest + 1)]);
            $this->attendance->linkUnknownLogs($employee);
        });
    }

    public function remove(Employee $employee, FingerprintTemplate $fingerprint): void
    {
        $fingerprint->setRelation('employee', $employee);
        $fingerprint->delete();
    }
}
