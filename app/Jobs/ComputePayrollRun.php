<?php

namespace App\Jobs;

use App\Models\PayrollRun;
use App\Services\Payroll\PayrollRunService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Computes a payroll run in the background. Recomputing is safe: the run's
 * items are replaced inside one transaction. If it fails, the run goes back
 * to draft with the reason so HR can try again.
 */
class ComputePayrollRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Payroll failures are data problems, not transient ones; do not retry.
     */
    public int $tries = 1;

    /**
     * Kept below the database queue's retry_after (90 seconds).
     */
    public int $timeout = 80;

    /**
     * Release the uniqueness lock after 10 minutes, so a lost job never
     * prevents the run from being queued again.
     */
    public int $uniqueFor = 600;

    public function __construct(public PayrollRun $run) {}

    public function uniqueId(): string
    {
        return (string) $this->run->id;
    }

    /**
     * Execute the job.
     */
    public function handle(PayrollRunService $payroll): void
    {
        $payroll->compute($this->run);
    }

    public function failed(?Throwable $exception): void
    {
        app(PayrollRunService::class)->markFailed($this->run, __('The computation failed: :reason', [
            'reason' => $exception?->getMessage() ?? __('unknown error'),
        ]));
    }
}
