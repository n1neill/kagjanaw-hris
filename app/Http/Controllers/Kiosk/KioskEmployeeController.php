<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Kiosk\KioskEmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Employees in service with a biometric user ID, for HR to pick at the
 * kiosk when enrolling fingerprints.
 */
class KioskEmployeeController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $employees = Employee::query()
            ->inService()
            ->whereNotNull('biometric_user_id')
            ->with(['department:id,name', 'fingerprintTemplates:id,employee_id,finger'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return KioskEmployeeResource::collection($employees);
    }
}
