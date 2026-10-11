<?php

namespace Database\Seeders;

use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

/**
 * Regular municipal office hours: 8:00-12:00 and 1:00-5:00, Monday to
 * Friday, with a 15-minute grace period. Editable by the System Administrator.
 */
class WorkScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WorkSchedule::current();
    }
}
