<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkScheduleRequest;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Office hours, grace period and working days used for every DTR.
 * Changes apply to days processed from now on; past days keep their totals
 * until HR recomputes them.
 */
class WorkScheduleController extends Controller
{
    public function edit(): View
    {
        return view('admin.work-schedule', [
            'schedule' => WorkSchedule::current(),
            'dayNames' => WorkSchedule::DAY_NAMES,
        ]);
    }

    public function update(WorkScheduleRequest $request): RedirectResponse
    {
        WorkSchedule::current()->update([
            ...$request->validated(),
            'working_days' => array_map('intval', $request->validated('working_days')),
        ]);

        return redirect()
            ->route('admin.work-schedule.edit')
            ->with('status', __('Office hours updated. They apply to attendance processed from now on.'));
    }
}
