<?php

namespace App\Http\Controllers\WorkCalendar;

use App\Enums\ChangeAction;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkCalendar\WorkScheduleRequest;
use App\Models\WorkSchedule;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Office hours, grace period and working days used for every DTR.
 * Changes apply to days processed from now on; past days keep their totals
 * until HR recomputes them.
 */
class WorkScheduleController extends Controller
{
    use SendsChangesForApproval;

    public function edit(): View
    {
        return view('work-calendar.schedule', [
            'schedule' => WorkSchedule::current(),
            'dayNames' => WorkSchedule::DAY_NAMES,
        ]);
    }

    public function update(WorkScheduleRequest $request, ChangeRequestService $changes): RedirectResponse
    {
        $outcome = $changes->perform($request->user(), ChangeAction::UpdateOfficeHours, $request->validated(), WorkSchedule::current());

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('work-calendar.schedule.edit'));
        }

        return redirect()
            ->route('work-calendar.schedule.edit')
            ->with('status', __('Office hours updated. They apply to attendance processed from now on.'));
    }
}
