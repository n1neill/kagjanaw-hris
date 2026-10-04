<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Common LGU plantilla positions with their usual salary grades.
 * VERIFY AGAINST CURRENT OFFICIAL TABLES (DBM Index of Occupational Services)
 * and the municipality's approved plantilla before production use.
 */
class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            'Municipal Mayor' => 27,
            'Municipal Vice Mayor' => 25,
            'Sangguniang Bayan Member' => 24,
            'Municipal Government Department Head I' => 24,
            'Administrative Officer V' => 18,
            'Administrative Officer IV' => 15,
            'Administrative Officer II' => 11,
            'Administrative Officer I' => 10,
            'Administrative Assistant II' => 8,
            'Administrative Assistant I' => 7,
            'Administrative Aide IV' => 4,
            'Administrative Aide III' => 3,
            'Administrative Aide I' => 1,
            'Human Resource Management Officer I' => 11,
            'Revenue Collection Clerk I' => 5,
            'Engineer I' => 12,
            'Agricultural Technologist' => 10,
            'Social Welfare Officer I' => 11,
            'Nurse I' => 15,
            'Midwife I' => 9,
        ];

        foreach ($positions as $title => $salaryGrade) {
            Position::updateOrCreate(['title' => $title], ['salary_grade' => $salaryGrade]);
        }
    }
}
