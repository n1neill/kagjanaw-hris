<?php

namespace App\Http\Controllers\Employees;

use App\Enums\Finger;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Attendance\Kiosk\FingerprintEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * HR removes an enrolled fingerprint (re-enrollment happens at the kiosk).
 */
class EmployeeFingerprintController extends Controller
{
    public function destroy(Employee $employee, Finger $finger, FingerprintEnrollmentService $enrollment): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $fingerprint = $employee->fingerprintTemplates()->where('finger', $finger)->firstOrFail();

        $enrollment->remove($employee, $fingerprint);

        return redirect()
            ->to(route('employees.show', $employee).'#fingerprints')
            ->with('status', __(':finger was removed. The kiosk stops accepting it within five minutes.', ['finger' => $finger->label()]));
    }
}
