<?php

namespace App\Services\Attendance\Kiosk;

use App\Enums\Finger;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use App\Models\User;

/**
 * Stores and removes enrolled fingerprints. Each change is audited through
 * the model (the template itself is never written to the audit log).
 */
class FingerprintEnrollmentService
{
    /**
     * Enroll a finger, replacing an earlier enrollment of the same finger.
     */
    public function enroll(Employee $employee, Finger $finger, string $template, ?int $quality, User $enrolledBy, ?BiometricDevice $device = null): FingerprintTemplate
    {
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

    public function remove(Employee $employee, FingerprintTemplate $fingerprint): void
    {
        $fingerprint->setRelation('employee', $employee);
        $fingerprint->delete();
    }
}
