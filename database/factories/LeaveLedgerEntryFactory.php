<?php

namespace Database\Factories;

use App\Enums\LeaveLedgerEntryType;
use App\Models\Employee;
use App\Models\LeaveLedgerEntry;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Entries should normally be posted through App\Services\Leave\LeaveLedger,
 * which also keeps the balance; this factory is for display-only tests.
 *
 * @extends Factory<LeaveLedgerEntry>
 */
class LeaveLedgerEntryFactory extends Factory
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
            'entry_type' => LeaveLedgerEntryType::Accrual,
            'entry_date' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            'period' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'days' => '1.250',
            'balance_after' => '1.250',
            'days_without_pay' => '0.000',
            'particulars' => 'Earned',
        ];
    }
}
