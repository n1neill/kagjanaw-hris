<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * The code a user enters after their password to finish signing in. It is
 * sent straight away rather than queued, so the user is not left waiting.
 */
class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code, public int $minutesValid) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your :app sign-in code', ['app' => config('app.name')]))
            ->line(__('Use this code to finish signing in:'))
            ->line(new HtmlString('<strong style="font-size: 24px; letter-spacing: 6px;">'.e($this->code).'</strong>'))
            ->line(__('The code expires in :minutes minutes.', ['minutes' => $this->minutesValid]))
            ->line(__('If you did not try to sign in, change your password and tell the System Administrator.'));
    }
}
