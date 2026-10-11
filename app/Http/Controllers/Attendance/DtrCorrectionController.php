<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CorrectDtrRequest;
use App\Models\Employee;
use App\Services\Attendance\DtrCorrectionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * HR's manual correction of one DTR day, recorded in an audit trail.
 */
class DtrCorrectionController extends Controller
{
    public function edit(Employee $employee, string $date, DtrCorrectionService $corrections): View
    {
        Gate::authorize('manageAttendance', $employee);

        $day = $this->correctableDate($employee, $date);
        $record = $corrections->recordFor($employee, $day);

        return view('attendance.correct', [
            'employee' => $employee,
            'day' => $day,
            'record' => $record,
            'punches' => $corrections->punchTimes($record),
            'rawLogs' => $employee->attendanceLogs()
                ->whereBetween('logged_at', [$day->startOfDay(), $day->endOfDay()])
                ->orderBy('logged_at')
                ->get(),
            'adjustments' => $record->exists
                ? $record->adjustments()->with('user')->latest()->latest('id')->get()
                : collect(),
        ]);
    }

    public function update(CorrectDtrRequest $request, Employee $employee, string $date, DtrCorrectionService $corrections): RedirectResponse
    {
        $day = $this->correctableDate($employee, $date);

        $corrections->correct($employee, $day, $request->punches(), $request->validated('reason'), $request->user());

        return redirect()
            ->route('attendance.dtr.show', [$employee, 'month' => $day->format('Y-m')])
            ->with('status', __('The DTR for :date was corrected.', ['date' => $day->format('F j, Y')]));
    }

    /**
     * Only past or current dates within the employee's service can be corrected.
     */
    private function correctableDate(Employee $employee, string $date): CarbonImmutable
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        abort_if($day === false || $day->format('Y-m-d') !== $date, 404);
        abort_if($day->isFuture() || ! $employee->wasEmployedOn($day), 404);

        return $day;
    }
}
