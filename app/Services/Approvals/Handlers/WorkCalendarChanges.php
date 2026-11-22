<?php

namespace App\Services\Approvals\Handlers;

use App\Models\Holiday;
use App\Models\WorkSchedule;
use App\Services\Approvals\ChangeContext;
use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Applies changes to holidays and office hours. Adding, moving or removing a
 * past or current holiday immediately recomputes that day's DTRs.
 */
class WorkCalendarChanges
{
    public function __construct(private AttendanceProcessor $processor) {}

    public function createHoliday(ChangeContext $change): Holiday
    {
        $holiday = Holiday::create($change->payload);

        $this->recompute($holiday->date);

        return $holiday;
    }

    public function updateHoliday(ChangeContext $change): Holiday
    {
        $holiday = $change->subject(Holiday::class);
        $previousDate = $holiday->date;

        $holiday->update($change->payload);

        $this->recompute($previousDate);
        $this->recompute($holiday->date);

        return $holiday;
    }

    public function deleteHoliday(ChangeContext $change): Holiday
    {
        $holiday = $change->subject(Holiday::class);
        $holiday->delete();

        $this->recompute($holiday->date);

        return $holiday;
    }

    public function updateOfficeHours(ChangeContext $change): WorkSchedule
    {
        $schedule = WorkSchedule::current();

        $schedule->update([
            ...$change->payload,
            'working_days' => array_map('intval', $change->input('working_days', [])),
        ]);

        return $schedule;
    }

    private function recompute(DateTimeInterface $date): void
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        if ($day->isFuture()) {
            return;
        }

        $this->processor->processAllEmployees($day, $day);
    }
}
