<?php

namespace App\Services\Attendance;

/**
 * Assigns a day's raw punches to the four DTR slots.
 *
 *  1. Punches before the midday boundary (halfway between the end of the
 *     morning and the start of the afternoon) belong to the morning session.
 *  2. Within a session, repeated taps a few minutes apart count once.
 *  3. In each session the first punch is "in" and the last is "out". A lone
 *     punch is "in" if it falls in the first half of the session, else "out".
 *
 * The device's own in/out state is ignored because employees often press the
 * wrong key; HR corrects any remaining ambiguity on the DTR.
 */
final class PunchAssigner
{
    /**
     * @param  list<int>  $punches  Minutes after midnight, in any order.
     */
    public function assign(array $punches, ScheduleRules $rules, int $duplicateTapMinutes): DayPunches
    {
        $boundary = $rules->middayBoundary();

        $morning = $this->withoutRepeatedTaps(
            array_values(array_filter($punches, fn (int $minute): bool => $minute < $boundary)),
            $duplicateTapMinutes,
        );
        $afternoon = $this->withoutRepeatedTaps(
            array_values(array_filter($punches, fn (int $minute): bool => $minute >= $boundary)),
            $duplicateTapMinutes,
        );

        [$amIn, $amOut] = $this->sessionPunches($morning, $rules->amStart, $rules->amEnd);
        [$pmIn, $pmOut] = $this->sessionPunches($afternoon, $rules->pmStart, $rules->pmEnd);

        return new DayPunches($amIn, $amOut, $pmIn, $pmOut);
    }

    /**
     * @param  list<int>  $punches
     * @return list<int>
     */
    private function withoutRepeatedTaps(array $punches, int $duplicateTapMinutes): array
    {
        sort($punches);

        $kept = [];

        foreach ($punches as $minute) {
            if ($kept === [] || $minute - end($kept) > $duplicateTapMinutes) {
                $kept[] = $minute;
            }
        }

        return $kept;
    }

    /**
     * @param  list<int>  $punches  Sorted punches within one session.
     * @return array{0: ?int, 1: ?int}
     */
    private function sessionPunches(array $punches, int $sessionStart, int $sessionEnd): array
    {
        return match (count($punches)) {
            0 => [null, null],
            1 => $punches[0] < intdiv($sessionStart + $sessionEnd, 2)
                ? [$punches[0], null]
                : [null, $punches[0]],
            default => [$punches[0], $punches[count($punches) - 1]],
        };
    }
}
