<?php

namespace App\Notifications;

use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An in-app (database) notification about a submitted change: HR Officers
 * hear that one is waiting; the submitter hears the decision.
 */
class ChangeRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ChangeRequest $changeRequest,
        public ChangeRequestStatus $status,
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
     * @return array{message: string, url: string, change_request_id: int, status: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'url' => route('approvals.show', $this->changeRequest, absolute: false),
            'change_request_id' => $this->changeRequest->id,
            'status' => $this->status->value,
        ];
    }

    private function message(): string
    {
        $change = $this->changeRequest;

        return match ($this->status) {
            ChangeRequestStatus::Pending => __(':name submitted a change for approval: :change.', [
                'name' => $change->requester->name,
                'change' => $change->description,
            ]),
            ChangeRequestStatus::Approved => __('Your change was approved and recorded: :change.', ['change' => $change->description]),
            ChangeRequestStatus::Rejected => __('Your change was not approved: :change.', ['change' => $change->description]),
            ChangeRequestStatus::Cancelled => __('A change was withdrawn: :change.', ['change' => $change->description]),
        };
    }
}
