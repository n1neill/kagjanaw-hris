<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\ThemePreference;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable;

    /**
     * New accounts follow the colour scheme of the user's computer.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'theme' => 'system',
    ];

    /**
     * How the record is named in the audit log.
     */
    public function auditLabel(): string
    {
        return $this->name.' ('.$this->username.')';
    }

    /**
     * Sign-ins are recorded as their own audit events, and the colour scheme
     * is a personal display setting.
     *
     * @return list<string>
     */
    public function auditExcluded(): array
    {
        return ['last_login_at', 'theme'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'theme' => ThemePreference::class,
        ];
    }

    /**
     * Whether signing in also needs a code emailed to the account's address.
     * Accounts without an email address sign in with their password alone.
     */
    public function requiresTwoFactor(): bool
    {
        return config('auth.two_factor.enabled') && filled($this->email);
    }

    /**
     * The employee record linked to this account, if the user is an employee.
     *
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * The highest-precedence role the user holds, used to pick a dashboard.
     */
    public function primaryRole(): ?Role
    {
        foreach (Role::cases() as $role) {
            if ($this->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Whether the user holds this permission only as a direct grant (not
     * through their role), so the changes they make with it must be approved
     * by an HR Officer before they are saved.
     */
    public function needsApprovalFor(Permission $permission): bool
    {
        return $this->hasDirectPermission($permission)
            && ! $this->getPermissionsViaRoles()->contains('name', $permission->value);
    }

    /**
     * Whether any change this user can make waits for an HR Officer's approval.
     */
    public function hasDelegatedAccess(): bool
    {
        return collect(Permission::grantable())
            ->contains(fn (Permission $permission): bool => $this->needsApprovalFor($permission));
    }

    /**
     * The permissions given to this account directly, on top of its role.
     *
     * @return list<Permission>
     */
    public function grantedPermissions(): array
    {
        return $this->loadMissing('permissions')->permissions
            ->map(fn ($permission): ?Permission => Permission::tryFrom($permission->name))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
