<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Balances should normally be created through App\Services\Leave\LeaveLedger
 * so the leave card matches; this factory is for display-only tests.
 *
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => fn (): int => LeaveType::vacation()->id,
            'balance' => '15.000',
        ];
    }
}
