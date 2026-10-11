<?php

namespace Database\Seeders;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * Philippine national holidays for 2026.
 * VERIFY AGAINST THE PRESIDENTIAL PROCLAMATION for the year, and add movable
 * holidays (Eid'l Fitr, Eid'l Adha) and local holidays of Cagdianao and the
 * Province of Dinagat Islands once they are declared.
 */
class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $holidays = [
            ['2026-01-01', "New Year's Day", HolidayType::Regular],
            ['2026-02-17', 'Chinese New Year', HolidayType::SpecialNonWorking],
            ['2026-04-02', 'Maundy Thursday', HolidayType::Regular],
            ['2026-04-03', 'Good Friday', HolidayType::Regular],
            ['2026-04-04', 'Black Saturday', HolidayType::SpecialNonWorking],
            ['2026-04-09', 'Araw ng Kagitingan', HolidayType::Regular],
            ['2026-05-01', 'Labor Day', HolidayType::Regular],
            ['2026-06-12', 'Independence Day', HolidayType::Regular],
            ['2026-08-21', 'Ninoy Aquino Day', HolidayType::SpecialNonWorking],
            ['2026-08-31', 'National Heroes Day', HolidayType::Regular],
            ['2026-11-01', "All Saints' Day", HolidayType::SpecialNonWorking],
            ['2026-11-02', "All Souls' Day", HolidayType::SpecialNonWorking],
            ['2026-11-30', 'Bonifacio Day', HolidayType::Regular],
            ['2026-12-08', 'Feast of the Immaculate Conception of Mary', HolidayType::SpecialNonWorking],
            ['2026-12-24', 'Christmas Eve', HolidayType::SpecialNonWorking],
            ['2026-12-25', 'Christmas Day', HolidayType::Regular],
            ['2026-12-30', 'Rizal Day', HolidayType::Regular],
            ['2026-12-31', 'Last Day of the Year', HolidayType::SpecialNonWorking],
        ];

        foreach ($holidays as [$date, $name, $type]) {
            Holiday::updateOrCreate(['date' => $date], ['name' => $name, 'type' => $type]);
        }
    }
}
