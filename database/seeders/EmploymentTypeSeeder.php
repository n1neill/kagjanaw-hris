<?php

namespace Database\Seeders;

use App\Models\EmploymentType;
use Illuminate\Database\Seeder;

/**
 * Natures of appointment recognized by the Civil Service Commission, plus
 * Job Order and Contract of Service engagements (not government service).
 */
class EmploymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'permanent' => ['Permanent', 'Plantilla appointment meeting all CSC qualification standards.'],
            'temporary' => ['Temporary', 'Plantilla appointment issued in the absence of qualified eligibles; up to 12 months.'],
            'coterminous' => ['Coterminous', 'Appointment coterminous with the appointing authority or a project.'],
            'casual' => ['Casual', 'Essential and necessary services where there are not enough regular staff.'],
            'contractual' => ['Contractual', 'Specific work or job within a definite period.'],
            'elective' => ['Elective', 'Elected officials.'],
            'job-order' => ['Job Order', 'Piece of work or intermittent job; not considered government service.'],
            'contract-of-service' => ['Contract of Service', 'Specific project or consultancy; not considered government service.'],
        ];

        foreach ($types as $code => [$name, $description]) {
            EmploymentType::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $description]);
        }
    }
}
