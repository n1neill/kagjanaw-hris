<?php

namespace Database\Factories;

use App\Enums\LeavePortion;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequestDay>
 */
class LeaveRequestDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'employee_id' => fn (array $attributes): int => LeaveRequest::find($attributes['leave_request_id'])->employee_id,
            'date' => now()->addWeeks(2)->startOfWeek()->toDateString(),
            'portion' => LeavePortion::FullDay,
            'days' => '1.000',
            'days_with_pay' => '0.000',
        ];
    }
}
