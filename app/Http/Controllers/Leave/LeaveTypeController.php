<?php

namespace App\Http\Controllers\Leave;

use App\Enums\ChangeAction;
use App\Enums\LeaveCreditSource;
use App\Enums\Sex;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveTypeRequest;
use App\Models\LeaveType;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Leave types and their rules, maintained by HR as regulations change.
 * Types are deactivated rather than deleted.
 */
class LeaveTypeController extends Controller
{
    use SendsChangesForApproval;

    public function index(): View
    {
        return view('leave.types.index', ['types' => LeaveType::query()->ordered()->get()]);
    }

    public function create(): View
    {
        return $this->form(new LeaveType([
            'credit_source' => LeaveCreditSource::NotCharged,
            'allows_half_day' => true,
            'advance_notice_days' => 0,
            'is_active' => true,
        ]));
    }

    public function store(LeaveTypeRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::CreateLeaveType, $request->validated());

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('leave.types.index'));
        }

        $type = $outcome->result;

        return redirect()->route('leave.types.index')->with('status', __(':name added.', ['name' => $type->name]));
    }

    public function edit(LeaveType $leaveType): View
    {
        return $this->form($leaveType);
    }

    public function update(LeaveTypeRequest $request, LeaveType $leaveType, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::UpdateLeaveType, $request->validated(), $leaveType);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('leave.types.index'));
        }

        return redirect()->route('leave.types.index')->with('status', __(':name updated.', ['name' => $leaveType->name]));
    }

    private function form(LeaveType $type): View
    {
        return view('leave.types.form', [
            'type' => $type,
            'sources' => LeaveCreditSource::options(),
            'sexes' => Sex::options(),
            'isFixed' => in_array($type->code, [LeaveType::VACATION, LeaveType::SICK, LeaveType::FORCED], true),
        ]);
    }
}
