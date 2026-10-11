<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\Leave\LeaveEndorsementRouter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The department head's (or Mayor's) queue of applications to endorse.
 */
class LeaveEndorsementController extends Controller
{
    public function __invoke(Request $request, LeaveEndorsementRouter $router): View
    {
        $user = $request->user();

        return view('leave.endorsements', [
            'awaiting' => $router->awaitingEndorsementBy($user)
                ->with(['employee.department', 'leaveType'])
                ->orderBy('date_from')
                ->orderBy('id')
                ->get(),
            'recent' => LeaveRequest::query()
                ->with(['employee.department', 'leaveType'])
                ->where('endorsed_by', $user->id)
                ->orderByDesc('endorsed_at')
                ->limit(25)
                ->get(),
        ]);
    }
}
