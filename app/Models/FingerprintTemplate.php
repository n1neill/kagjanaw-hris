<?php

namespace App\Models;

use App\Enums\Finger;
use App\Models\Concerns\Auditable;
use Database\Factories\FingerprintTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One enrolled finger of an employee, captured at a DigitalPersona kiosk.
 * The template is the matcher's (SourceAFIS) feature data, base64 encoded
 * and encrypted at rest; fingerprint images are never stored (RA 10173).
 */
#[Fillable(['finger', 'template', 'quality', 'enrolled_by', 'biometric_device_id'])]
#[Hidden(['template'])]
class FingerprintTemplate extends Model
{
    /** @use HasFactory<FingerprintTemplateFactory> */
    use Auditable, HasFactory;

    /**
     * How the record is named in the audit log.
     */
    public function auditLabel(): string
    {
        return __(':finger of employee :number', [
            'finger' => $this->finger->label(),
            'number' => $this->employee?->employee_number,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'finger' => Finger::class,
            'template' => 'encrypted',
            'quality' => 'integer',
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
    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /**
     * @return BelongsTo<BiometricDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }
}
