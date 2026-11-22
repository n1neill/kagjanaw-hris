<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * HR Officers review every submitted change; a submitter sees and may
 * withdraw their own. Nobody reviews a change they submitted.
 */
class ChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ApproveChanges)
            || $user->hasDelegatedAccess()
            || ChangeRequest::query()->where('requested_by', $user->id)->exists();
    }

    public function view(User $user, ChangeRequest $change): Response
    {
        return $user->can(Permission::ApproveChanges) || $change->isRequestedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function review(User $user, ChangeRequest $change): bool
    {
        return $user->can(Permission::ApproveChanges)
            && $change->isPending()
            && ! $change->isRequestedBy($user);
    }

    public function cancel(User $user, ChangeRequest $change): bool
    {
        return $change->isRequestedBy($user) && $change->isPending();
    }
}
