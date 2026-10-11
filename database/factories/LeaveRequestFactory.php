<?php

namespace Database\Factories;

use App\Enums\LeaveEndorsementRoute;
use App\Enums\LeavePortion;
use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A vacation leave application with one row per weekday it covers. For the
 * full workflow (credits, DTR, notifications) use LeaveRequestService instead.
 *
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = CarbonImmutable::today()->addWeeks(2)->startOfWeek();

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => fn (): int => LeaveType::vacation()->id,
            'date_from' => $from->toDateString(),
            'date_to' => $from->addDays(2)->toDateString(),
            'portion' => LeavePortion::FullDay,
            'days' => '3.000',
            'details' => 'Family vacation within the Philippines.',
            'status' => LeaveRequestStatus::Pending,
            'endorsement_route' => LeaveEndorsementRoute::DepartmentHead,
        ];
    }

    /**
     * Cover the given dates (weekdays only), keeping "days" in step.
     */
    public function between(string $from, string $to): static
    {
        $weekdays = collect(CarbonPeriod::create($from, $to))->reject(fn ($date): bool => $date->isWeekend())->count();

        return $this->state(fn (array $attributes) => [
            'date_from' => $from,
            'date_to' => $to,
            'days' => sprintf('%d.000', $weekdays),
        ]);
    }

    public function endorsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveRequestStatus::Endorsed,
            'endorsed_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveRequestStatus::Approved,
            'days_with_pay' => $attributes['days'],
            'days_without_pay' => '0.000',
            'endorsed_at' => now(),
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeaveRequestStatus::Rejected,
            'decided_at' => now(),
            'decision_remarks' => 'Exigency of the service.',
        ]);
    }

    /**
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterCreating(function (LeaveRequest $request): void {
            $isApproved = $request->status === LeaveRequestStatus::Approved;

            foreach (CarbonPeriod::create($request->date_from, $request->date_to) as $date) {
                if ($date->isWeekend()) {
                    continue;
                }

                $days = $request->portion === LeavePortion::FullDay ? '1.000' : '0.500';

                $request->leaveDays()->create([
                    'employee_id' => $request->employee_id,
                    'date' => $date->toDateString(),
                    'portion' => $request->portion,
                    'days' => $days,
                    'days_with_pay' => $isApproved ? $days : '0.000',
                ]);
            }
        });
    }
}
