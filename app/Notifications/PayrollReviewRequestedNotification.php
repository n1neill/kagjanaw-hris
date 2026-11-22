<?php

namespace App\Notifications;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells HR Officers that a user with granted payroll access computed a
 * payroll, which now needs an HR Officer's review and approval.
 */
class PayrollReviewRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PayrollRun $run,
        public User $preparedBy,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{message: string, url: string, payroll_run_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':name queued :payroll for computation. Review and approve it before it is released.', [
                'name' => $this->preparedBy->name,
                'payroll' => $this->run->title(),
            ]),
            'url' => route('payroll.runs.show', $this->run, absolute: false),
            'payroll_run_id' => $this->run->id,
        ];
    }
}
