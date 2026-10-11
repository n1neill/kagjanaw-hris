<?php

namespace Database\Factories;

use App\Models\DailyTimeRecord;
use App\Models\DailyTimeRecordAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyTimeRecordAdjustment>
 */
class DailyTimeRecordAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_time_record_id' => DailyTimeRecord::factory(),
            'user_id' => null,
            'changes' => ['pm_out' => ['from' => null, 'to' => '17:00']],
            'reason' => 'Forgot to punch out; confirmed by department head.',
        ];
    }
}
