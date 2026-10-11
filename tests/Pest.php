<?php

use App\Enums\DtrStatus;
use App\Enums\LeaveLedgerEntryType;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Leave\LeaveDays;
use App\Services\Leave\LeaveLedger;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Leave helpers
|--------------------------------------------------------------------------
*/

/**
 * Give an employee an opening vacation and sick leave balance (in days).
 */
function grantLeaveCredits(Employee $employee, string $vacation = '10.000', string $sick = '10.000', string $asOf = '2026-08-31'): void
{
    $ledger = app(LeaveLedger::class);
    $date = CarbonImmutable::parse($asOf);

    $ledger->post($employee, LeaveType::vacation(), LeaveLedgerEntryType::OpeningBalance, LeaveDays::toThousandths($vacation), 'Opening balance', $date);
    $ledger->post($employee, LeaveType::sick(), LeaveLedgerEntryType::OpeningBalance, LeaveDays::toThousandths($sick), 'Opening balance', $date);
}

/**
 * A validated DTR for every day of a month: weekdays present (or as the
 * callback returns), weekends rest days.
 *
 * @param  (callable(CarbonImmutable): array<string, mixed>)|null  $weekday  Attribute overrides per weekday.
 */
function validatedMonthOfDtr(Employee $employee, string $month, ?callable $weekday = null): void
{
    $start = CarbonImmutable::parse($month)->startOfMonth();

    foreach (CarbonPeriod::create($start, $start->endOfMonth()->startOfDay()) as $date) {
        $date = CarbonImmutable::parse($date);
        $attributes = $date->isWeekend()
            ? ['am_in' => null, 'am_out' => null, 'pm_in' => null, 'pm_out' => null, 'worked_minutes' => 0, 'status' => DtrStatus::RestDay]
            : ($weekday === null ? [] : $weekday($date));

        DailyTimeRecord::factory()->for($employee)->create([
            'work_date' => $date->toDateString(),
            'validated_at' => now(),
            ...$attributes,
        ]);
    }
}
