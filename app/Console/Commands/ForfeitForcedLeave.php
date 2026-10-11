<?php

namespace App\Console\Commands;

use App\Services\Leave\ForcedLeaveService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Forfeits the mandatory (forced) leave employees with 10 or more days of
 * vacation leave did not take during the year. Scheduled in mid-January so
 * December leave can be approved first. Running it twice has no effect.
 */
#[Signature('leave:forfeit-forced-leave {--year= : Year to close (default: last year)}')]
#[Description('Forfeit unused mandatory (forced) leave for a year')]
class ForfeitForcedLeave extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ForcedLeaveService $forcedLeave): int
    {
        $year = $this->option('year') ?? now()->subYear()->year;

        if (! is_numeric($year) || (int) $year < 2000 || (int) $year >= now()->year) {
            $this->error('--year must be a past year.');

            return self::INVALID;
        }

        $count = $forcedLeave->forfeitUnused((int) $year);

        $this->info("Forced leave for {$year}: credits forfeited for {$count} employees.");

        return self::SUCCESS;
    }
}
