<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Standard municipal offices under the Local Government Code (RA 7160).
 * Verify against the Municipality of Cagdianao's actual organizational structure.
 */
class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            'OMM' => 'Office of the Municipal Mayor',
            'OMVM' => 'Office of the Municipal Vice Mayor',
            'SB' => 'Office of the Sangguniang Bayan',
            'MHRMO' => 'Municipal Human Resource Management Office',
            'MPDO' => 'Municipal Planning and Development Office',
            'MTO' => "Municipal Treasurer's Office",
            'MACCO' => 'Municipal Accounting Office',
            'MBO' => 'Municipal Budget Office',
            'MASSO' => "Municipal Assessor's Office",
            'MCR' => 'Office of the Municipal Civil Registrar',
            'MSWDO' => 'Municipal Social Welfare and Development Office',
            'MHO' => 'Municipal Health Office',
            'MAO' => 'Municipal Agriculture Office',
            'MEO' => 'Municipal Engineering Office',
            'MDRRMO' => 'Municipal Disaster Risk Reduction and Management Office',
            'GSO' => 'General Services Office',
            'MENRO' => 'Municipal Environment and Natural Resources Office',
            'MTOUR' => 'Municipal Tourism Office',
        ];

        foreach ($departments as $code => $name) {
            Department::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
