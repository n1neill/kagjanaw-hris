<?php

namespace App\Http\Controllers\Employees;

use App\Enums\ChangeAction;
use App\Enums\Finger;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * HR removes an enrolled fingerprint (re-enrollment happens at the kiosk).
 */
class EmployeeFingerprintController extends Controller
{
    use SendsChangesForApproval;

    public function destroy(Request $request, Employee $employee, Finger $finger, ChangeRequestService $changes): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $fingerprint = $employee->fingerprintTemplates()->where('finger', $finger)->firstOrFail();

        $outcome = $changes->perform($request->user(), ChangeAction::RemoveFingerprint, ['finger' => $finger], $fingerprint);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('employees.show', $employee).'#fingerprints');
        }

        return redirect()
            ->to(route('employees.show', $employee).'#fingerprints')
            ->with('status', __(':finger was removed. The kiosk stops accepting it within five minutes.', ['finger' => $finger->label()]));
    }
}
