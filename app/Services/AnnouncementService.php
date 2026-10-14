<?php

namespace App\Services;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Saves announcements and notifies their audience the first time one is
 * published. Editing a published announcement does not notify again.
 */
class AnnouncementService
{
    /**
     * @param  array{title: string, body: string, audience: AnnouncementAudience, is_pinned: bool, expires_on: ?string, departments: list<int>}  $data
     */
    public function create(array $data, bool $publish, User $author): Announcement
    {
        return $this->save(new Announcement(['created_by' => $author->id]), $data, $publish);
    }

    /**
     * @param  array{title: string, body: string, audience: AnnouncementAudience, is_pinned: bool, expires_on: ?string, departments: list<int>}  $data
     */
    public function update(Announcement $announcement, array $data, bool $publish): Announcement
    {
        return $this->save($announcement, $data, $publish);
    }

    /**
     * Active users the announcement is addressed to, except its author.
     *
     * @return Builder<User>
     */
    public function recipients(Announcement $announcement): Builder
    {
        return User::query()
            ->active()
            ->when($announcement->created_by, fn (Builder $query, int $authorId) => $query->whereKeyNot($authorId))
            ->when($announcement->audience === AnnouncementAudience::Departments, fn (Builder $query) => $query
                ->whereHas('employee', fn (Builder $employees) => $employees->whereIn('department_id', $announcement->departments()->pluck('departments.id'))));
    }

    /**
     * @param  array{title: string, body: string, audience: AnnouncementAudience, is_pinned: bool, expires_on: ?string, departments: list<int>}  $data
     */
    private function save(Announcement $announcement, array $data, bool $publish): Announcement
    {
        $isFirstPublication = $publish && $announcement->published_at === null;

        DB::transaction(function () use ($announcement, $data, $isFirstPublication): void {
            $announcement->fill([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $data['audience'],
                'is_pinned' => $data['is_pinned'],
                'expires_on' => $data['expires_on'],
            ]);

            if ($isFirstPublication) {
                $announcement->published_at = now();
            }

            $announcement->save();
            $announcement->departments()->sync($data['departments']);
        });

        if ($isFirstPublication) {
            $this->recipients($announcement)->chunkById(200, fn ($users) => Notification::send($users, new AnnouncementPublishedNotification($announcement)));
        }

        return $announcement;
    }
}
