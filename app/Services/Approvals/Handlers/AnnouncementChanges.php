<?php

namespace App\Services\Approvals\Handlers;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Services\Approvals\ChangeContext;

/**
 * Applies changes to announcements. Publishing notifies the recipients.
 */
class AnnouncementChanges
{
    public function __construct(private AnnouncementService $announcements) {}

    public function createAnnouncement(ChangeContext $change): Announcement
    {
        return $this->announcements->create($this->announcement($change), (bool) $change->input('publish'), $change->author);
    }

    public function updateAnnouncement(ChangeContext $change): Announcement
    {
        $announcement = $change->subject(Announcement::class);
        $publish = (bool) $change->input('publish') || $announcement->published_at !== null;

        return $this->announcements->update($announcement, $this->announcement($change), $publish);
    }

    public function deleteAnnouncement(ChangeContext $change): void
    {
        $change->subject(Announcement::class)->delete();
    }

    /**
     * @return array{title: string, body: string, audience: AnnouncementAudience, is_pinned: bool, expires_on: ?string, departments: list<int>}
     */
    private function announcement(ChangeContext $change): array
    {
        return [
            'title' => $change->input('title'),
            'body' => $change->input('body'),
            'audience' => AnnouncementAudience::from($change->input('audience')),
            'is_pinned' => (bool) $change->input('is_pinned'),
            'expires_on' => $change->input('expires_on'),
            'departments' => array_map('intval', $change->input('departments', [])),
        ];
    }
}
