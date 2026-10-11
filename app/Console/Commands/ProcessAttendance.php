<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Builds Daily Time Records for every employee, including days with no
 * punches (absences). Scheduled shortly after midnight to finalize the
 * previous day; can also be run by hand for any range.
 */
#[Signature('attendance:process {--from= : First date (Y-m-d), default yesterday} {--to= : Last date (Y-m-d), default today}')]
#[Description('Compute Daily Time Records from attendance logs')]
class ProcessAttendance extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AttendanceProcessor $processor): int
    {
        try {
            $from = CarbonImmutable::parse($this->option('from') ?? 'yesterday')->startOfDay();
            $to = CarbonImmutable::parse($this->option('to') ?? 'today')->startOfDay();
        } catch (Throwable) {
            $this->error('Dates must be in Y-m-d format.');

            return self::INVALID;
        }

        if ($from->greaterThan($to)) {
            $this->error('--from must not be after --to.');

            return self::INVALID;
        }

        $count = $processor->processAllEmployees($from, $to);

        $this->info("Processed {$count} employees from {$from->toDateString()} to {$to->toDateString()}.");

        return self::SUCCESS;
    }
}
