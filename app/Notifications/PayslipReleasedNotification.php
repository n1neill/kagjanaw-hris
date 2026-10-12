<?php

namespace App\Notifications;

use App\Models\PayrollItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An in-app (database) notification that a payslip is ready. The message
 * names the pay period, never the amount.
 */
class PayslipReleasedNotification extends Notification
{
    use Queueable;

    public function __construct(public PayrollItem $item) {}

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
     * @return array{message: string, url: string, payroll_item_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('Your payslip for :period is ready.', ['period' => $this->item->run->period->label()]),
            'url' => route('payroll.payslips.my', absolute: false),
            'payroll_item_id' => $this->item->id,
        ];
    }
}
