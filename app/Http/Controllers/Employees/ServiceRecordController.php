<?php

namespace App\Http\Controllers\Employees;

use App\Enums\AuditEvent;
use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\Concerns\RendersServiceRecord;
use App\Http\Requests\Employees\ServiceRecordRequest;
use App\Models\Employee;
use App\Models\EmployeeServiceRecord;
use App\Services\Approvals\ChangeRequestService;
use App\Services\Audit\AuditLogger;
use App\Services\ServiceRecordService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The CSC service record of an employee. HR keeps the entries; HR and the
 * employee can view and print it.
 */
class ServiceRecordController extends Controller
{
    use RendersServiceRecord, SendsChangesForApproval;

    public function show(Request $request, Employee $employee, AuditLogger $audit): View
    {
        Gate::authorize('viewServiceRecord', $employee);

        $audit->accessed(AuditEvent::Viewed, __('Service record of :employee', ['employee' => $employee->auditLabel()]), $employee, $employee);

        return $this->renderServiceRecord($request->user(), $employee);
    }

    /**
     * The printable service record with the HRMO certification.
     */
    public function pdf(Request $request, Employee $employee, AuditLogger $audit): Response
    {
        Gate::authorize('viewServiceRecord', $employee);

        $employee->load(['department', 'position']);

        $audit->accessed(AuditEvent::Downloaded, __('Service record of :employee', ['employee' => $employee->auditLabel()]), $employee, $employee);

        $pdf = Pdf::loadView('employees.service-record-pdf', [
            'employee' => $employee,
            'entries' => $employee->serviceRecords()->orderBy('service_from')->orderBy('id')->get(),
            'issuedBy' => $request->user(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->stream(sprintf('service-record-%s.pdf', $employee->employee_number));
    }

    public function create(Employee $employee, ServiceRecordService $serviceRecords): View
    {
        return view('employees.service-record-form', [
            'employee' => $employee,
            'entry' => new EmployeeServiceRecord($serviceRecords->defaultsFor($employee)),
        ]);
    }

    public function store(ServiceRecordRequest $request, Employee $employee, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::AddServiceRecord, $request->validated(), $employee);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('employees.service-record.show', $employee));
        }

        return redirect()->route('employees.service-record.show', $employee)
            ->with('status', __('Service record entry added.'));
    }

    public function edit(Employee $employee, EmployeeServiceRecord $serviceRecord): View
    {
        return view('employees.service-record-form', ['employee' => $employee, 'entry' => $serviceRecord]);
    }

    public function update(ServiceRecordRequest $request, Employee $employee, EmployeeServiceRecord $serviceRecord, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::UpdateServiceRecord, $request->validated(), $serviceRecord);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('employees.service-record.show', $employee));
        }

        return redirect()->route('employees.service-record.show', $employee)
            ->with('status', __('Service record entry updated.'));
    }

    public function destroy(Request $request, Employee $employee, EmployeeServiceRecord $serviceRecord, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::DeleteServiceRecord, [], $serviceRecord);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('employees.service-record.show', $employee));
        }

        return redirect()->route('employees.service-record.show', $employee)
            ->with('status', __('Service record entry deleted.'));
    }
}
