<?php

namespace App\Services\Leave;

use App\Enums\LeaveCreditSource;
use App\Enums\LeavePortion;
use App\Models\Employee;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;

/**
 * Checks an application for leave against the rules of its leave type.
 * Returns the covered days so the caller does not count them twice.
 */
class LeaveApplicationValidator
{
    /**
     * Longest period one application may cover, in calendar days.
     */
    public const MAX_SPAN_DAYS = 366;

    public function __construct(
        private LeaveDayCounter $counter,
        private LeaveBalanceService $balances,
    ) {}

    /**
     * @return array{errors: array<string, string>, days: list<array{date: CarbonImmutable, portion: LeavePortion, thousandths: int}>, total: int}
     */
    public function check(Employee $employee, LeaveType $type, CarbonImmutable $from, CarbonImmutable $to, LeavePortion $portion, bool $hasAttachment): array
    {
        $result = fn (array $errors, array $days = []): array => [
            'errors' => $errors,
            'days' => $days,
            'total' => array_sum(array_column($days, 'thousandths')),
        ];

        if (! $type->is_active) {
            return $result(['leave_type_id' => __('This leave type is no longer available.')]);
        }

        if ($type->eligible_sex !== null && $employee->sex !== $type->eligible_sex) {
            return $result(['leave_type_id' => __(':type is only available to :sex employees.', [
                'type' => $type->name,
                'sex' => mb_strtolower($type->eligible_sex->label()),
            ])]);
        }

        if (in_array($type->credit_source, [LeaveCreditSource::VacationCredits, LeaveCreditSource::SickCredits], true) && ! $employee->earnsLeaveCredits()) {
            return $result(['leave_type_id' => __('Your employment type does not earn vacation or sick leave credits.')]);
        }

        if ($from->diffInDays($to) >= self::MAX_SPAN_DAYS) {
            return $result(['date_to' => __('One application may cover at most :days days. File separate applications.', ['days' => self::MAX_SPAN_DAYS])]);
        }

        if ($portion !== LeavePortion::FullDay && (! $from->isSameDay($to) || ! $type->allows_half_day)) {
            return $result(['portion' => $type->allows_half_day
                ? __('Half-day leave must start and end on the same date.')
                : __(':type cannot be taken by half day.', ['type' => $type->name])]);
        }

        $today = CarbonImmutable::today();

        if (! $type->allows_past_dates && $from->lessThan($today)) {
            return $result(['date_from' => __(':type must be filed before the leave starts.', ['type' => $type->name])]);
        }

        if ($type->advance_notice_days > 0 && $from->lessThan($today->addDays($type->advance_notice_days))) {
            return $result(['date_from' => __(':type must be filed at least :days days before the leave starts (on or before :date).', [
                'type' => $type->name,
                'days' => $type->advance_notice_days,
                'date' => $from->subDays($type->advance_notice_days)->format('M j, Y'),
            ])]);
        }

        if (! $employee->wasEmployedOn($from) || ! $employee->wasEmployedOn($to)) {
            return $result(['date_from' => __('The leave must fall within your period of service.')]);
        }

        $days = $this->counter->days($type, $from, $to, $portion);
        $total = array_sum(array_column($days, 'thousandths'));

        if ($days === []) {
            return $result(['date_from' => __('The selected dates are all rest days or holidays.')]);
        }

        if ($type->max_days_per_request !== null && $total > LeaveDays::toThousandths($type->max_days_per_request)) {
            return $result(['date_to' => __(':type may cover at most :max days per application; these dates cover :days.', [
                'type' => $type->name,
                'max' => LeaveDays::format($type->max_days_per_request),
                'days' => LeaveDays::format($total),
            ])], $days);
        }

        if ($type->annual_days !== null) {
            $remaining = LeaveDays::toThousandths($type->annual_days) - $this->balances->usedInYear($employee, $type, $from->year);

            if ($total > $remaining) {
                return $result(['date_to' => __('You have :remaining days of :type left for :year; these dates cover :days.', [
                    'remaining' => LeaveDays::format(max(0, $remaining)),
                    'type' => $type->name,
                    'year' => $from->year,
                    'days' => LeaveDays::format($total),
                ])], $days);
            }
        }

        $overlap = LeaveRequestDay::query()
            ->where('employee_id', $employee->id)
            ->whereIn('date', array_map(fn (array $day): string => $day['date']->toDateString(), $days))
            ->whereHas('leaveRequest', fn ($query) => $query->holdingDates())
            ->orderBy('date')
            ->first();

        if ($overlap !== null) {
            return $result(['date_from' => __('You already have a leave application covering :date.', ['date' => $overlap->date->format('M j, Y')])], $days);
        }

        if ($type->requiresAttachmentFor($total) && ! $hasAttachment) {
            return $result(['attachment' => $type->attachment_hint ?? __('Attach the supporting document for this leave.')], $days);
        }

        return $result([], $days);
    }
}
