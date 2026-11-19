<?php

namespace Database\Seeders;

use App\Models\Department;
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
            'Municipal Mayor' => [27, 'OMM'],
            'Municipal Vice Mayor' => [25, 'OMVM'],
            'Sangguniang Bayan Member' => [24, 'SB'],
            'Municipal Government Department Head I' => [24, null],
            'Administrative Officer V' => [18, null],
            'Administrative Officer IV' => [15, null],
            'Administrative Officer II' => [11, null],
            'Administrative Officer I' => [10, null],
            'Administrative Assistant II' => [8, null],
            'Administrative Assistant I' => [7, null],
            'Administrative Aide IV' => [4, null],
            'Administrative Aide III' => [3, null],
            'Administrative Aide I' => [1, null],
            'Human Resource Management Officer I' => [11, 'MHRMO'],
            'Revenue Collection Clerk I' => [5, 'MTO'],
            'Accountant I' => [11, 'MACCO'],
            'Accounting Clerk I' => [5, 'MACCO'],
            'Budget Officer I' => [11, 'MBO'],
            'Engineer I' => [12, 'MEO'],
            'Agricultural Technologist' => [10, 'MAO'],
            'Social Welfare Officer I' => [11, 'MSWDO'],
            'Nurse I' => [15, 'MHO'],
            'Midwife I' => [9, 'MHO'],
        ];

        foreach ($positions as $title => [$salaryGrade, $departmentCode]) {
            Position::updateOrCreate(['title' => $title], [
                'salary_grade' => $salaryGrade,
                'department_id' => $departmentCode ? Department::query()->where('code', $departmentCode)->value('id') : null,
            ]);
        }
    }
}
