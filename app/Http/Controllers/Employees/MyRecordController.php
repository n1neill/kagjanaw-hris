<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\Concerns\RendersEmployeeRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The signed-in employee's own record and 201 file.
 */
class MyRecordController extends Controller
{
    use RendersEmployeeRecord;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $employee = $request->user()->employee;

        if ($employee === null) {
            return view('employees.no-record');
        }

        Gate::authorize('view', $employee);

        return $this->renderEmployeeRecord($request->user(), $employee);
    }
}
