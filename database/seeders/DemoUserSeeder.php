<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One login per role for demos and local development. Never run in production.
 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'Kagjanaw@2026';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            'admin@cagdianao.local' => ['System Administrator', Role::SystemAdministrator],
            'hr@cagdianao.local' => ['Maria Santos', Role::HrOfficer],
            'depthead@cagdianao.local' => ['Juan Dela Cruz', Role::DepartmentHead],
            'employee@cagdianao.local' => ['Ana Reyes', Role::Employee],
        ];

        foreach ($accounts as $email => [$name, $role]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => self::PASSWORD,
                'is_active' => true,
            ]);

            $user->syncRoles([$role]);
        }
    }
}
