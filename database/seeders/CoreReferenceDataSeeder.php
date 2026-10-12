<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data the application cannot run without: roles, permissions and
 * leave types and payroll tables. Also seeded for every feature test.
 */
class CoreReferenceDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LeaveTypeSeeder::class,
            PayrollReferenceDataSeeder::class,
        ]);
    }
}
