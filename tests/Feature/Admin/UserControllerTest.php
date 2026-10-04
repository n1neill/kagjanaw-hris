<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('index', function () {
    it('lists accounts with their role and status', function () {
        $admin = User::factory()->systemAdministrator()->create();
        User::factory()->hrOfficer()->create(['name' => 'Maria Santos']);
        User::factory()->employee()->inactive()->create(['name' => 'Pedro Penduko']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertSeeInOrder(['Maria Santos', 'HR Officer', 'Active'])
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
    it('creates an active account with the chosen role', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Juana Dela Cruz',
            'email' => 'juana@cagdianao.local',
            'role' => Role::DepartmentHead->value,
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', 'Account for Juana Dela Cruz created.');

        $user = User::where('email', 'juana@cagdianao.local')->sole();
        expect($user->name)->toBe('Juana Dela Cruz')
            ->and($user->is_active)->toBeTrue()
            ->and($user->primaryRole())->toBe(Role::DepartmentHead)
            ->and(Hash::check('Initial-Passw0rd!', $user->password))->toBeTrue();
    });

    it('rejects an empty submission', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), []);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'email' => 'The email field is required.',
            'role' => 'The role field is required.',
            'password' => 'The password field is required.',
        ]);
        $this->assertDatabaseCount('users', 1);
    });

    it('rejects an email address that is already used', function () {
        $admin = User::factory()->systemAdministrator()->create();
        User::factory()->create(['email' => 'taken@cagdianao.local']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Someone',
            'email' => 'taken@cagdianao.local',
            'role' => Role::Employee->value,
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]);

        $response->assertSessionHasErrors(['email' => 'The email has already been taken.']);
        $this->assertDatabaseCount('users', 2);
    });

    it('rejects a role that does not exist', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Someone',
            'email' => 'someone@cagdianao.local',
            'role' => 'super-admin',
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]);

        $response->assertSessionHasErrors(['role' => 'The selected role is invalid.']);
        $this->assertDatabaseMissing('users', ['email' => 'someone@cagdianao.local']);
    });

    it('rejects a password that does not meet the password policy', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Someone',
            'email' => 'someone@cagdianao.local',
            'role' => Role::Employee->value,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['password' => 'The password field must be at least 12 characters.']);
        $this->assertDatabaseMissing('users', ['email' => 'someone@cagdianao.local']);
    });

    it('forbids an HR officer from creating accounts', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();

        $response = $this->actingAs($hrOfficer)->post(route('admin.users.store'), [
            'name' => 'Sneaky Admin',
            'email' => 'sneaky@cagdianao.local',
            'role' => Role::SystemAdministrator->value,
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@cagdianao.local']);
    });
});

describe('update', function () {
    it('updates details and role while keeping the password when left blank', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $account = User::factory()->employee()->create();
        $originalPasswordHash = $account->password;

        $response = $this->actingAs($admin)->put(route('admin.users.update', $account), [
            'name' => 'Renamed Person',
            'email' => 'renamed@cagdianao.local',
            'role' => Role::HrOfficer->value,
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $account->refresh();
        expect($account->name)->toBe('Renamed Person')
            ->and($account->email)->toBe('renamed@cagdianao.local')
            ->and($account->getRoleNames()->all())->toBe([Role::HrOfficer->value])
            ->and($account->password)->toBe($originalPasswordHash);
    });

    it('resets the password when a new one is given', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $account = User::factory()->employee()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $account), [
            'name' => $account->name,
            'email' => $account->email,
            'role' => Role::Employee->value,
            'password' => 'Reset-Passw0rd!!',
            'password_confirmation' => 'Reset-Passw0rd!!',
        ]);

        expect(Hash::check('Reset-Passw0rd!!', $account->refresh()->password))->toBeTrue();
    });

    it('lets administrators edit their own details', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => 'Head Administrator',
            'email' => $admin->email,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        expect($admin->refresh()->name)->toBe('Head Administrator')
            ->and($admin->primaryRole())->toBe(Role::SystemAdministrator);
    });

    it('prevents administrators from changing their own role', function () {
        $admin = User::factory()->systemAdministrator()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => Role::Employee->value,
        ]);

        $response->assertSessionHasErrors(['role' => 'The role field is prohibited.']);
        expect($admin->refresh()->primaryRole())->toBe(Role::SystemAdministrator);
    });

    it('forbids an employee from editing another account', function () {
        $employee = User::factory()->employee()->create();
        $account = User::factory()->employee()->create(['name' => 'Original Name']);

        $response = $this->actingAs($employee)->put(route('admin.users.update', $account), [
            'name' => 'Hijacked',
            'email' => $account->email,
            'role' => Role::SystemAdministrator->value,
        ]);

        $response->assertForbidden();
        expect($account->refresh()->name)->toBe('Original Name')
            ->and($account->primaryRole())->toBe(Role::Employee);
    });
});
