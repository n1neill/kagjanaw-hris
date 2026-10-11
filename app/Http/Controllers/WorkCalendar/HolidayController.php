<?php

namespace App\Http\Controllers\WorkCalendar;

use App\Enums\HolidayType;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkCalendar\HolidayRequest;
use App\Models\Holiday;
use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Non-working holidays. Adding, moving or removing a past or current holiday
 * immediately recomputes that day's DTRs.
 */
class HolidayController extends Controller
{
    public function __construct(private AttendanceProcessor $processor) {}

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
        $holiday = Holiday::create($request->validated());

        $this->recompute($holiday->date);

        return redirect()->route('work-calendar.holidays.index')->with('status', __(':name added.', ['name' => $holiday->name]));
    }

    public function edit(Holiday $holiday): View
    {
        return view('work-calendar.holidays.form', ['holiday' => $holiday, 'types' => HolidayType::options()]);
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $previousDate = $holiday->date;

        $holiday->update($request->validated());

        $this->recompute($previousDate);
        $this->recompute($holiday->date);

        return redirect()->route('work-calendar.holidays.index')->with('status', __(':name updated.', ['name' => $holiday->name]));
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        $this->recompute($holiday->date);

        return redirect()->route('work-calendar.holidays.index')->with('status', __(':name removed.', ['name' => $holiday->name]));
    }

    private function recompute(\DateTimeInterface $date): void
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        if ($day->isFuture()) {
            return;
        }

        $this->processor->processAllEmployees($day, $day);
    }
}
