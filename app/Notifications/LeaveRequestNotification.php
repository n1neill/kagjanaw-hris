<?php

namespace App\Notifications;

use App\Enums\LeaveRequestEvent;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An in-app (database) notification about a step in the leave workflow.
 * The message names the leave type and dates, never the reason given.
 */
class LeaveRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest,
        public LeaveRequestEvent $event,
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
     * @return array{message: string, url: string, leave_request_id: int, event: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message($notifiable),
            'url' => route('leave.requests.show', $this->leaveRequest, absolute: false),
            'leave_request_id' => $this->leaveRequest->id,
            'event' => $this->event->value,
        ];
    }

    private function message(object $notifiable): string
    {
        $request = $this->leaveRequest;
        $leave = $request->leaveType->name.', '.$request->periodLabel();
        $isOwn = $notifiable instanceof User && $request->isFiledBy($notifiable);

        if ($isOwn) {
            return match ($this->event) {
                LeaveRequestEvent::Filed => __('Your application for :leave was filed.', ['leave' => $leave]),
                LeaveRequestEvent::Endorsed => __('Your :leave was endorsed and sent to HR.', ['leave' => $leave]),
                LeaveRequestEvent::Rejected => __('Your :leave was not approved.', ['leave' => $leave]),
                LeaveRequestEvent::Approved => __('Your :leave was approved.', ['leave' => $leave]),
                LeaveRequestEvent::Cancelled => __('Your :leave was cancelled.', ['leave' => $leave]),
            };
        }

        $name = $request->employee->full_name;

        return match ($this->event) {
            LeaveRequestEvent::Filed => __(':name applied for :leave and needs your endorsement.', ['name' => $name, 'leave' => $leave]),
            LeaveRequestEvent::Endorsed => __(':name\'s :leave is ready for HR approval.', ['name' => $name, 'leave' => $leave]),
            LeaveRequestEvent::Rejected => __(':name\'s :leave was not approved.', ['name' => $name, 'leave' => $leave]),
            LeaveRequestEvent::Approved => __(':name\'s :leave was approved.', ['name' => $name, 'leave' => $leave]),
            LeaveRequestEvent::Cancelled => __(':name\'s :leave was cancelled.', ['name' => $name, 'leave' => $leave]),
        };
    }
}
