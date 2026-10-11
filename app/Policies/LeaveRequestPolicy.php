<?php

namespace App\Policies;

use App\Enums\LeaveEndorsementRoute;
use App\Enums\LeaveRequestStatus;
use App\Enums\Permission;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Leave\LeaveEndorsementRouter;
use Illuminate\Auth\Access\Response;

/**
 * Who may see and act on a leave application:
 *  - the employee who filed it;
 *  - whoever endorses it (their department head, or the Mayor);
 *  - HR officers.
 *
 * Applications a user may not see respond as "not found".
 */
class LeaveRequestPolicy
{
    public function __construct(private LeaveEndorsementRouter $router) {}

    public function view(User $user, LeaveRequest $request): Response
    {
        $allowed = $request->isFiledBy($user)
            || $user->can(Permission::ApproveLeave)
            || $this->isEndorserFor($user, $request);

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    public function endorse(User $user, LeaveRequest $request): bool
    {
        return $this->router->canEndorse($user, $request);
    }

    /**
     * Approve or reject an endorsed application. HR officers cannot decide their own.
     */
    public function decide(User $user, LeaveRequest $request): bool
    {
        return $request->status === LeaveRequestStatus::Endorsed
            && $user->can(Permission::ApproveLeave)
            && ! $request->isFiledBy($user);
    }

    /**
     * Reject at whichever stage the application is waiting.
     */
    public function reject(User $user, LeaveRequest $request): bool
    {
        return $this->endorse($user, $request) || $this->decide($user, $request);
    }

    /**
     * The employee may withdraw an application still waiting for a decision;
     * HR may cancel an approved one (restoring the credits).
     */
    public function cancel(User $user, LeaveRequest $request): bool
    {
        if ($request->status->isOpen()) {
            return $request->isFiledBy($user);
        }

        return $request->status === LeaveRequestStatus::Approved
            && $user->can(Permission::ApproveLeave)
            && ! $request->isFiledBy($user);
    }

    /**
     * The department head (or Mayor) who endorses — or endorsed — the
     * application may read it, including its history.
     */
    private function isEndorserFor(User $user, LeaveRequest $request): bool
    {
        if ($request->endorsed_by !== null && $request->endorsed_by === $user->id) {
            return true;
        }

        return match ($request->endorsement_route) {
            LeaveEndorsementRoute::DepartmentHead => $user->can(Permission::EndorseLeave)
                && $user->employee?->department_id !== null
                && $user->employee->department_id === $request->employee->department_id
                && ! $request->isFiledBy($user),
            LeaveEndorsementRoute::MunicipalMayor => $user->can(Permission::EndorseDepartmentHeadLeave),
            LeaveEndorsementRoute::NotRequired => false,
        };
    }
}
