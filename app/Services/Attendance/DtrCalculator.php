<?php

namespace App\Services\Attendance;

use App\Enums\DtrStatus;

/**
 * Computes tardiness, undertime, absence and hours worked for one day,
 * following the Civil Service rules applied by the Municipality:
 *
 *  - Each session (morning, afternoon) is judged on its own.
 *  - Arriving within the grace period is on time. Arriving after it is late,
 *    counted from the scheduled start of the session.
 *  - Leaving before the end of the session is undertime.
 *  - A session with no punches is an absence for that half-day.
 *  - A session with only one punch cannot be verified: it counts as absent
 *    and the day is flagged "missing punch" for HR to correct.
 *  - Arriving early or leaving late earns no extra credit.
 *  - Holidays and rest days carry no deductions.
 *  - Today is "in progress": sessions that have not yet ended are not
 *    judged, except that a late arrival is shown as soon as it happens.
 */
final class DtrCalculator
{
    /**
     * @param  int|null  $nowMinutes  Minutes after midnight when the day is today; null for past days.
     */
    public function calculate(DayPunches $punches, ScheduleRules $rules, DayType $dayType, ?int $nowMinutes = null): DtrResult
    {
        $morning = $this->session($punches->amIn, $punches->amOut, $rules->amStart, $rules->amEnd, $rules->graceMinutes, $nowMinutes);
        $afternoon = $this->session($punches->pmIn, $punches->pmOut, $rules->pmStart, $rules->pmEnd, $rules->graceMinutes, $nowMinutes);

        $workedMinutes = $morning['worked'] + $afternoon['worked'];

        if ($dayType !== DayType::Working) {
            return new DtrResult(
                status: $dayType === DayType::Holiday ? DtrStatus::Holiday : DtrStatus::RestDay,
                workedMinutes: $workedMinutes,
            );
        }

        $outcomes = [$morning['outcome'], $afternoon['outcome']];

        $status = match (true) {
            in_array('open', $outcomes, true) => DtrStatus::Pending,
            in_array('incomplete', $outcomes, true) => DtrStatus::Incomplete,
            $outcomes === ['absent', 'absent'] => DtrStatus::Absent,
            in_array('absent', $outcomes, true) => DtrStatus::HalfDay,
            default => DtrStatus::Present,
        };

        return new DtrResult(
            status: $status,
            lateMinutes: $morning['late'] + $afternoon['late'],
            undertimeMinutes: $morning['undertime'] + $afternoon['undertime'],
            absentMinutes: $morning['absent'] + $afternoon['absent'],
            workedMinutes: $workedMinutes,
        );
    }

    /**
     * @return array{outcome: 'complete'|'absent'|'incomplete'|'open', late: int, undertime: int, absent: int, worked: int}
     */
    private function session(?int $in, ?int $out, int $start, int $end, int $grace, ?int $nowMinutes): array
    {
        $length = $end - $start;
        $late = ($in !== null && $in > $start + $grace) ? min($in - $start, $length) : 0;

        if ($nowMinutes !== null && $nowMinutes < $end) {
            return ['outcome' => 'open', 'late' => $late, 'undertime' => 0, 'absent' => 0, 'worked' => 0];
        }

        if ($in === null && $out === null) {
            return ['outcome' => 'absent', 'late' => 0, 'undertime' => 0, 'absent' => $length, 'worked' => 0];
        }

        if ($in === null || $out === null || $out <= $in) {
            return ['outcome' => 'incomplete', 'late' => 0, 'undertime' => 0, 'absent' => $length, 'worked' => 0];
        }

        return [
            'outcome' => 'complete',
            'late' => $late,
            'undertime' => $out < $end ? min($end - $out, $length) : 0,
            'absent' => 0,
            'worked' => max(0, min($out, $end) - max($in, $start)),
        ];
    }
}
