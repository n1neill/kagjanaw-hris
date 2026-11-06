<?php

namespace App\Http\Resources\Kiosk;

use App\Models\FingerprintTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An enrolled finger as the kiosk matches against it.
 *
 * @mixin FingerprintTemplate
 */
class KioskTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{employee_id: int, biometric_user_id: string, employee_name: string, finger: string, template: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->employee_id,
            'biometric_user_id' => $this->employee->biometric_user_id,
            'employee_name' => $this->employee->full_name,
            'finger' => $this->finger->value,
            'template' => $this->template,
        ];
    }
}
