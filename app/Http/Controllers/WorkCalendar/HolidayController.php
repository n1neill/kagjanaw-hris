<?php

namespace App\Http\Controllers\WorkCalendar;

use App\Enums\ChangeAction;
use App\Enums\HolidayType;
use App\Http\Controllers\Concerns\SendsChangesForApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkCalendar\HolidayRequest;
use App\Models\Holiday;
use App\Services\Approvals\ChangeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Non-working holidays. Adding, moving or removing a past or current holiday
 * immediately recomputes that day's DTRs.
 */
class HolidayController extends Controller
{
    use SendsChangesForApproval;

    public function __construct(private ChangeRequestService $changes) {}

    public function index(): View
    {
        return view('work-calendar.holidays.index', [
            'holidays' => Holiday::query()->orderByDesc('date')->get(),
        ]);
    }

    public function create(): View
    {
        return view('work-calendar.holidays.form', ['holiday' => new Holiday, 'types' => HolidayType::options()]);
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        $outcome = $this->changes->perform($request->user(), ChangeAction::CreateHoliday, $request->validated());

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('work-calendar.holidays.index'));
        }

        $holiday = $outcome->result;

        return redirect()->route('work-calendar.holidays.index')->with('status', __(':name added.', ['name' => $holiday->name]));
    }

    public function edit(Holiday $holiday): View
    {
        return view('work-calendar.holidays.form', ['holiday' => $holiday, 'types' => HolidayType::options()]);
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $outcome = $this->changes->perform($request->user(), ChangeAction::UpdateHoliday, $request->validated(), $holiday);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('work-calendar.holidays.index'));
        }

        return redirect()->route('work-calendar.holidays.index')->with('status', __(':name updated.', ['name' => $holiday->name]));
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $outcome = $this->changes->perform($request->user(), ChangeAction::DeleteHoliday, [], $holiday);

        if ($outcome->isQueued()) {
            return $this->sentForApproval(route('work-calendar.holidays.index'));
        }

        return redirect()->route('work-calendar.holidays.index')->with('status', __(':name removed.', ['name' => $holiday->name]));
    }
}
