<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employees\Concerns\RendersServiceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The signed-in employee's own service record (view and print only).
 */
class MyServiceRecordController extends Controller
{
    use RendersServiceRecord;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $employee = $request->user()->employee;

        if ($employee === null) {
            return view('employees.no-record');
        }

        Gate::authorize('viewServiceRecord', $employee);

        return $this->renderServiceRecord($request->user(), $employee);
    }
}
