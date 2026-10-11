<?php

namespace App\Services\Leave;

use App\Enums\LeaveEndorsementRoute;
use App\Enums\LeaveRequestStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides who endorses a leave application before HR acts on it:
 *
 *  - Employees: the head of their department.
 *  - Department heads, and employees of an office with no department head
 *    in the system: the Municipal Mayor.
 *  - The Municipal Mayor: no endorsement; the application goes straight to HR.
 *    (Also used when no Mayor account exists, so applications never get stuck.)
 */
class LeaveEndorsementRouter
{
    public function routeFor(Employee $employee): LeaveEndorsementRoute
    {
        $user = $employee->user;

        if ($user?->hasRole(Role::MunicipalMayor)) {
            return LeaveEndorsementRoute::NotRequired;
        }

        if (! $user?->hasRole(Role::DepartmentHead) && $this->departmentHeads($employee, $user)->isNotEmpty()) {
            return LeaveEndorsementRoute::DepartmentHead;
        }

        return $this->mayors()->isNotEmpty()
            ? LeaveEndorsementRoute::MunicipalMayor
            : LeaveEndorsementRoute::NotRequired;
    }

    /**
     * The users who may endorse the application now.
     *
     * @return Collection<int, User>
     */
    public function endorsersFor(LeaveRequest $request): Collection
    {
        return match ($request->endorsement_route) {
            LeaveEndorsementRoute::DepartmentHead => $this->departmentHeads($request->employee, $request->employee->user),
            LeaveEndorsementRoute::MunicipalMayor => $this->mayors()->reject(fn (User $user): bool => $request->isFiledBy($user))->values(),
            LeaveEndorsementRoute::NotRequired => new Collection,
        };
    }

    public function canEndorse(User $user, LeaveRequest $request): bool
    {
        if ($request->status !== LeaveRequestStatus::Pending || $request->isFiledBy($user)) {
            return false;
        }

        return match ($request->endorsement_route) {
            LeaveEndorsementRoute::DepartmentHead => $user->can(Permission::EndorseLeave)
                && $user->employee?->department_id !== null
                && $user->employee->department_id === $request->employee->department_id,
            LeaveEndorsementRoute::MunicipalMayor => $user->can(Permission::EndorseDepartmentHeadLeave),
            LeaveEndorsementRoute::NotRequired => false,
        };
    }

    /**
     * Applications waiting for this user's endorsement (never their own).
     *
     * @return Builder<LeaveRequest>
     */
    public function awaitingEndorsementBy(User $user): Builder
    {
        $departmentId = $user->employee?->department_id;
        $endorsesDepartment = $user->can(Permission::EndorseLeave) && $departmentId !== null;
        $endorsesHeads = $user->can(Permission::EndorseDepartmentHeadLeave);

        return LeaveRequest::query()
            ->where('status', LeaveRequestStatus::Pending)
            ->whereHas('employee', fn ($employees) => $employees->where(fn ($owner) => $owner->whereNull('user_id')->orWhere('user_id', '!=', $user->id)))
            ->where(function (Builder $query) use ($endorsesDepartment, $endorsesHeads, $departmentId): void {
                $query->whereRaw('1 = 0');

                if ($endorsesDepartment) {
                    $query->orWhere(fn (Builder $own) => $own
                        ->where('endorsement_route', LeaveEndorsementRoute::DepartmentHead)
                        ->whereHas('employee', fn ($employees) => $employees->where('department_id', $departmentId)));
                }

                if ($endorsesHeads) {
                    $query->orWhere('endorsement_route', LeaveEndorsementRoute::MunicipalMayor);
                }
            });
    }

    /**
     * Active HR officers, who decide endorsed applications.
     *
     * @return Collection<int, User>
     */
    public function hrOfficers(): Collection
    {
        return User::query()->active()->permission(Permission::ApproveLeave->value)->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function departmentHeads(Employee $employee, ?User $except): Collection
    {
        return User::query()
            ->active()
            ->permission(Permission::EndorseLeave->value)
            ->whereHas('employee', fn ($query) => $query->where('department_id', $employee->department_id))
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function mayors(): Collection
    {
        return User::query()->active()->permission(Permission::EndorseDepartmentHeadLeave->value)->get();
    }
}
