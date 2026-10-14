<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An in-app (database) notification that HR posted an announcement.
 */
class AnnouncementPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

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
     * @return array{message: string, url: string, announcement_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('New announcement: :title', ['title' => $this->announcement->title]),
            'url' => route('announcements.show', $this->announcement, absolute: false),
            'announcement_id' => $this->announcement->id,
        ];
    }
}
