<?php

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;

describe('edit', function () {
    it('lists the grantable permissions, marking those the role already includes', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $account = User::factory()->employee()->create();
        $account->givePermissionTo(Permission::ProcessPayroll->value);

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $account));

        $response->assertSee('Additional access')
            ->assertSee('Manage employees')
            ->assertSee('value="payroll.process"', escape: false)
            ->assertDontSee('value="users.manage"', escape: false);
    });

    it('does not offer additional access on the administrator\'s own account', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $admin));

        $response->assertOk()->assertDontSee('Additional access');
    });
});

describe('update', function () {
    it('grants and removes permissions immediately, recording the change', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $account = User::factory()->employee()->create(['name' => 'Ana Reyes']);
        $account->givePermissionTo(Permission::ManageAttendance->value);

        $response = $this->actingAs($admin)->put(route('admin.users.permissions', $account), [
            'permissions' => [Permission::ManageEmployees->value, Permission::ProcessPayroll->value],
        ]);

        $response->assertRedirect(route('admin.users.edit', $account))
            ->assertSessionHas('status', 'Additional access for Ana Reyes updated.');

        $account = $account->fresh();
        expect($account->can(Permission::ManageEmployees))->toBeTrue()
            ->and($account->can(Permission::ProcessPayroll))->toBeTrue()
            ->and($account->can(Permission::ManageAttendance))->toBeFalse();

        $entry = AuditLog::query()->where('event', AuditEvent::Updated)->latest('id')->first();
        expect($entry->old_values)->toBe(['additional_access' => 'Manage attendance'])
            ->and($entry->new_values)->toBe(['additional_access' => 'Manage employees, Process payroll']);
    });

    it('removes every grant when no permission is checked', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $account = User::factory()->employee()->create();
        $account->givePermissionTo(Permission::ManageEmployees->value);

        $this->actingAs($admin)->put(route('admin.users.permissions', $account), []);

        expect($account->fresh()->can(Permission::ManageEmployees))->toBeFalse();
    });

    it('rejects permissions outside HR administration', function (Permission $permission) {
        $admin = User::factory()->systemAdministrator()->create();
        $account = User::factory()->employee()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.permissions', $account), [
            'permissions' => [$permission->value],
        ]);

        $response->assertSessionHasErrors('permissions.0');
        expect($account->fresh()->can($permission))->toBeFalse();
    })->with([
        'manage users' => Permission::ManageUsers,
        'manage backups' => Permission::ManageBackups,
        'approve changes' => Permission::ApproveChanges,
    ]);

    it('forbids administrators from granting access to themselves', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.permissions', $admin), [
            'permissions' => [Permission::ProcessPayroll->value],
        ]);

        $response->assertForbidden();
        expect($admin->fresh()->can(Permission::ProcessPayroll))->toBeFalse();
    });

    it('forbids roles without the manage users permission', function () {
        $hrOfficer = User::factory()->withRole(Role::HrOfficer)->create();
        $account = User::factory()->employee()->create();

        $response = $this->actingAs($hrOfficer)->put(route('admin.users.permissions', $account), [
            'permissions' => [Permission::ProcessPayroll->value],
        ]);

        $response->assertForbidden();
        expect($account->fresh()->can(Permission::ProcessPayroll))->toBeFalse();
    });
});
