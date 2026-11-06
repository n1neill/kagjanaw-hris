<?php

namespace App\Http\Resources\Kiosk;

use App\Models\Employee;
use App\Models\FingerprintTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee HR can pick at the kiosk to enroll fingerprints for.
 *
 * @mixin Employee
 */
class KioskEmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, employee_number: string, name: string, department: string|null, biometric_user_id: string, enrolled_fingers: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'name' => $this->sortable_name,
            'department' => $this->department?->name,
            'biometric_user_id' => $this->biometric_user_id,
            'enrolled_fingers' => $this->fingerprintTemplates
                ->map(fn (FingerprintTemplate $fingerprint): string => $fingerprint->finger->value)
                ->values()
                ->all(),
        ];
    }
}
