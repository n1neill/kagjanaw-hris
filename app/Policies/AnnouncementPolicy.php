<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Announcements: HR posts and manages them; everyone else reads the current
 * ones addressed to them. Others respond as "not found".
 */
class AnnouncementPolicy
{
    public function view(User $user, Announcement $announcement): Response
    {
        if ($user->can(Permission::ManageAnnouncements)) {
            return Response::allow();
        }

        return Announcement::query()->visibleTo($user)->whereKey($announcement->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageAnnouncements);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->can(Permission::ManageAnnouncements);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->can(Permission::ManageAnnouncements);
    }
}
