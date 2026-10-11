<?php

namespace App\Http\Controllers\Leave;

use App\Enums\LeaveCreditSource;
use App\Enums\Sex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveTypeRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Leave types and their rules, maintained by HR as regulations change.
 * Types are deactivated rather than deleted.
 */
class LeaveTypeController extends Controller
{
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

    public function store(LeaveTypeRequest $request): RedirectResponse
    {
        $type = LeaveType::create([
            ...$request->validated(),
            'sort_order' => (int) LeaveType::max('sort_order') + 10,
        ]);

        return redirect()->route('leave.types.index')->with('status', __(':name added.', ['name' => $type->name]));
    }

    public function edit(LeaveType $leaveType): View
    {
        return $this->form($leaveType);
    }

    public function update(LeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($request->validated());

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
