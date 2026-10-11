<?php

namespace App\Http\Controllers\Employees\Concerns;

use App\Enums\DocumentType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\View\View;

/**
 * Shared by the HR record page and the employee's own "My Record" page.
 */
trait RendersEmployeeRecord
{
    /**
     * Render an employee record, showing sensitive details and the 201 file
     * only to viewers allowed to see them.
     */
    protected function renderEmployeeRecord(User $viewer, Employee $employee): View
    {
        $employee->load(['department', 'position', 'employmentType', 'user']);

        $canViewSensitive = $viewer->can('viewSensitive', $employee);

        return view('employees.show', [
            'employee' => $employee,
            'canViewSensitive' => $canViewSensitive,
            'canManage' => $viewer->can('update', $employee),
            'canBrowseEmployees' => $viewer->can('viewAny', Employee::class),
            'isOwnRecord' => $employee->user_id === $viewer->id,
            'documents' => $canViewSensitive
                ? $employee->documents()->with('uploader')->latest()->latest('id')->get()
                : collect(),
            'documentTypes' => DocumentType::options(),
        ]);
    }
}
