<?php

namespace App\Http\Controllers\Employees\Concerns;

use App\Models\Employee;
use App\Models\User;
use Illuminate\View\View;

/**
 * Shared by the HR service record page and the employee's own "My Service Record" page.
 */
trait RendersServiceRecord
{
    /**
     * Render the service record, with editing controls only for viewers allowed to manage it.
     */
    protected function renderServiceRecord(User $viewer, Employee $employee): View
    {
        $employee->load(['department', 'position', 'employmentType']);

        return view('employees.service-record', [
            'employee' => $employee,
            'entries' => $employee->serviceRecords()->orderBy('service_from')->orderBy('id')->get(),
            'canManage' => $viewer->can('manageServiceRecord', $employee),
            'isOwnRecord' => $employee->user_id === $viewer->id,
        ]);
    }
}
