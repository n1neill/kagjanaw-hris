<?php

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('index', function () {
    it('lists accounts with their username, role and status', function () {
        $admin = User::factory()->systemAdministrator()->create();
        User::factory()->hrOfficer()->create(['name' => 'Maria Santos', 'username' => '2015-0101']);
        User::factory()->employee()->inactive()->create(['name' => 'Pedro Penduko']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertSeeInOrder(['Maria Santos', '2015-0101', 'HR Officer', 'Active'])
            ->assertSeeInOrder(['Pedro Penduko', 'Employee', 'Inactive']);
    });

    it('escapes user-provided names', function () {
        $admin = User::factory()->systemAdministrator()->create();
        User::factory()->employee()->create(['name' => '<script>alert("x")</script>']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertSee('&lt;script&gt;', escape: false)
            ->assertDontSee('<script>alert("x")</script>', escape: false);
    });

    it('redirects guests to the login page', function () {
        $response = $this->get(route('admin.users.index'));

        $response->assertRedirect(route('login'));
    });

    it('forbids roles without the manage users permission', function (Role $role) {
        $user = User::factory()->withRole($role)->create();

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
    })->with([
        'hr officer' => Role::HrOfficer,
        'department head' => Role::DepartmentHead,
        'employee' => Role::Employee,
    ]);
});

describe('store', function () {
    it('creates an active account that must change its temporary password', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Juana Dela Cruz',
            'username' => 'juana.delacruz',
            'email' => 'juana@cagdianao.local',
            'role' => Role::DepartmentHead->value,
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', 'Account for Juana Dela Cruz created.');

        $user = User::where('username', 'juana.delacruz')->sole();
        expect($user->name)->toBe('Juana Dela Cruz')
            ->and($user->email)->toBe('juana@cagdianao.local')
            ->and($user->is_active)->toBeTrue()
            ->and($user->must_change_password)->toBeTrue()
            ->and($user->primaryRole())->toBe(Role::DepartmentHead)
            ->and(Hash::check('Initial-Passw0rd!', $user->password))->toBeTrue();
    });

    it('creates an account without an email address', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Records Kiosk',
            'username' => 'kiosk',
            'role' => Role::Employee->value,
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ])->assertSessionHasNoErrors();

        expect(User::where('username', 'kiosk')->sole()->email)->toBeNull();
    });

    it('rejects an empty submission', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), []);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'username' => 'The username field is required.',
            'role' => 'The role field is required.',
            'password' => 'The password field is required.',
        ]);
        $this->assertDatabaseCount('users', 1);
    });

    it('rejects a username or email that is already used', function () {
        $admin = User::factory()->systemAdministrator()->create();
        User::factory()->create(['username' => 'taken', 'email' => 'taken@cagdianao.local']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Someone',
            'username' => 'taken',
            'email' => 'taken@cagdianao.local',
            'role' => Role::Employee->value,
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]);

        $response->assertSessionHasErrors([
            'username' => 'The username has already been taken.',
            'email' => 'The email has already been taken.',
        ]);
        $this->assertDatabaseCount('users', 2);
    });

    it('rejects a username with spaces or symbols', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Someone',
            'username' => 'some one!',
            'role' => Role::Employee->value,
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]);

        $response->assertSessionHasErrors(['username' => 'The username may only contain letters, numbers, dots, dashes and underscores.']);
        $this->assertDatabaseCount('users', 1);
    });

    it('rejects a role that does not exist', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Someone',
            'username' => 'someone',
            'role' => 'super-admin',
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]);

        $response->assertSessionHasErrors(['role' => 'The selected role is invalid.']);
        $this->assertDatabaseMissing('users', ['username' => 'someone']);
    });

    it('rejects a password that does not meet the password policy', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Someone',
            'username' => 'someone',
            'role' => Role::Employee->value,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['password' => 'The password field must be at least 12 characters.']);
        $this->assertDatabaseMissing('users', ['username' => 'someone']);
    });

    it('forbids an HR officer from creating accounts', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('admin.users.store'), [
            'name' => 'Sneaky Admin',
            'username' => 'sneaky',
            'role' => Role::SystemAdministrator->value,
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'sneaky']);
    });
});

describe('update', function () {
    it('updates details and role while keeping the password when left blank', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $account = User::factory()->employee()->create();
        $originalPasswordHash = $account->password;

        $response = $this->actingAs($admin)->put(route('admin.users.update', $account), [
            'name' => 'Renamed Person',
            'username' => 'renamed',
            'email' => 'renamed@cagdianao.local',
            'role' => Role::HrOfficer->value,
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $account->refresh();
        expect($account->name)->toBe('Renamed Person')
            ->and($account->username)->toBe('renamed')
            ->and($account->email)->toBe('renamed@cagdianao.local')
            ->and($account->getRoleNames()->all())->toBe([Role::HrOfficer->value])
            ->and($account->password)->toBe($originalPasswordHash)
            ->and($account->must_change_password)->toBeFalse();
    });

    it('resets the password and requires the user to change it', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $account = User::factory()->employee()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $account), [
            'name' => $account->name,
            'username' => $account->username,
            'role' => Role::Employee->value,
            'password' => 'Reset-Passw0rd!!',
            'password_confirmation' => 'Reset-Passw0rd!!',
        ]);

        $account->refresh();
        expect(Hash::check('Reset-Passw0rd!!', $account->password))->toBeTrue()
            ->and($account->must_change_password)->toBeTrue();
    });

    it('lets administrators edit their own details', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => 'Head Administrator',
            'username' => $admin->username,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        expect($admin->refresh()->name)->toBe('Head Administrator')
            ->and($admin->primaryRole())->toBe(Role::SystemAdministrator);
    });

    it('prevents administrators from changing their own role', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'username' => $admin->username,
            'role' => Role::Employee->value,
        ]);

        $response->assertSessionHasErrors(['role' => 'The role field is prohibited.']);
        expect($admin->refresh()->primaryRole())->toBe(Role::SystemAdministrator);
    });

    it('lets administrators promote an employee account without touching the name or username', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $employee = Employee::factory()->withAccount()->create(['employee_number' => '2010-0045']);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $employee->user), [
            'role' => Role::DepartmentHead->value,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $account = $employee->user->refresh();
        expect($account->primaryRole())->toBe(Role::DepartmentHead)
            ->and($account->username)->toBe('2010-0045');
    });

    it('prohibits renaming an account that belongs to an employee', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $employee = Employee::factory()->withAccount()->create(['employee_number' => '2010-0045']);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $employee->user), [
            'name' => 'Someone Else',
            'username' => 'someone',
            'role' => Role::Employee->value,
        ]);

        $response->assertSessionHasErrors([
            'name' => 'The name field is prohibited.',
            'username' => 'The username field is prohibited.',
        ]);
        expect($employee->user->refresh()->username)->toBe('2010-0045');
    });

    it('forbids an employee from editing another account', function () {
        $employee = User::factory()->employee()->create();
        $account = User::factory()->employee()->create(['name' => 'Original Name']);

        $response = $this->actingAs($employee)->put(route('admin.users.update', $account), [
            'name' => 'Hijacked',
            'username' => $account->username,
            'role' => Role::SystemAdministrator->value,
        ]);

        $response->assertForbidden();
        expect($account->refresh()->name)->toBe('Original Name')
            ->and($account->primaryRole())->toBe(Role::Employee);
    });
});
