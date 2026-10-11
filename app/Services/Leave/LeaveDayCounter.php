<?php

namespace App\Services\Leave;

use App\Enums\LeavePortion;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Lists the days a leave application covers. Most leave counts working days
 * only (rest days and holidays are skipped); leave granted in calendar days,
 * such as the 105 days of Maternity Leave, counts every day.
 */
class LeaveDayCounter
{
    /**
     * @return list<array{date: CarbonImmutable, portion: LeavePortion, thousandths: int}>
     */
    public function days(LeaveType $type, CarbonImmutable $from, CarbonImmutable $to, LeavePortion $portion = LeavePortion::FullDay): array
    {
        if ($to->lessThan($from)) {
            return [];
        }

        $isHalfDay = $portion !== LeavePortion::FullDay && $from->isSameDay($to);
        $portion = $isHalfDay ? $portion : LeavePortion::FullDay;

        $schedule = WorkSchedule::current();
        $holidays = $type->counts_calendar_days
            ? []
            : Holiday::query()
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->pluck('date')
                ->map(fn ($date): string => $date->toDateString())
                ->all();

        $days = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $date = CarbonImmutable::parse($date);

            $isWorkingDay = $schedule->isWorkingDay($date->dayOfWeekIso)
                && ! in_array($date->toDateString(), $holidays, true);

            if ($type->counts_calendar_days || $isWorkingDay) {
                $days[] = ['date' => $date, 'portion' => $portion, 'thousandths' => $portion->thousandths()];
            }
        }

        return $days;
    }
}
