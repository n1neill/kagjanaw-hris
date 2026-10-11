<?php

namespace App\Console\Commands;

use App\Services\Leave\LeaveCreditPostingService;
use App\Services\Leave\LeavePostingResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Posts a finished month's leave credits for every employee whose DTR for
 * the month has been validated. Safe to run repeatedly; scheduled daily so
 * months validated late are still posted.
 */
#[Signature('leave:post-credits {--month= : Month to post, as YYYY-MM (default: last month)}')]
#[Description('Post monthly leave credits and tardiness deductions from validated DTRs')]
class PostLeaveCredits extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LeaveCreditPostingService $posting): int
    {
        $option = $this->option('month');

        if ($option !== null && CarbonImmutable::canBeCreatedFromFormat($option, 'Y-m') === false) {
            $this->error('--month must be in YYYY-MM format.');

            return self::INVALID;
        }

        $month = $option === null
            ? CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $option)->startOfMonth();

        $results = $posting->postMonthForAll($month);

        $posted = $results->filter(fn (array $row): bool => $row['result']->wasPosted())->count();
        $waiting = $results->filter(fn (array $row): bool => $row['result']->status === LeavePostingResult::SKIPPED)->count();

        $this->info("{$month->format('F Y')}: {$posted} employees posted, {$waiting} not ready.");

        return self::SUCCESS;
    }
}
