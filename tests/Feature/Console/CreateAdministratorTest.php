<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an active system administrator who keeps the chosen password', function () {
    $this->artisan('app:create-administrator', ['--name' => 'MHRMO Admin', '--username' => 'mhrmo-admin', '--email' => 'mhrmo@cagdianao.gov.ph'])
        ->expectsQuestion('Password (at least 12 characters with upper and lower case letters, a number and a symbol)', 'Adm1n-Passw0rd!')
        ->expectsQuestion('Confirm password', 'Adm1n-Passw0rd!')
        ->expectsOutput('System Administrator account "mhrmo-admin" created.')
        ->assertSuccessful();

    $admin = User::where('username', 'mhrmo-admin')->sole();
    expect($admin->name)->toBe('MHRMO Admin')
        ->and($admin->email)->toBe('mhrmo@cagdianao.gov.ph')
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->must_change_password)->toBeFalse()
        ->and($admin->primaryRole())->toBe(Role::SystemAdministrator)
        ->and(Hash::check('Adm1n-Passw0rd!', $admin->password))->toBeTrue();
});

it('asks for the name and username when they are not given', function () {
    $this->artisan('app:create-administrator')
        ->expectsQuestion('Full name', 'MHRMO Admin')
        ->expectsQuestion('Username', 'mhrmo-admin')
        ->expectsQuestion('Password (at least 12 characters with upper and lower case letters, a number and a symbol)', 'Adm1n-Passw0rd!')
        ->expectsQuestion('Confirm password', 'Adm1n-Passw0rd!')
        ->assertSuccessful();

    expect(User::where('username', 'mhrmo-admin')->sole()->email)->toBeNull();
});

it('refuses a weak password and creates no account', function () {
    $this->artisan('app:create-administrator', ['--name' => 'MHRMO Admin', '--username' => 'mhrmo-admin'])
        ->expectsQuestion('Password (at least 12 characters with upper and lower case letters, a number and a symbol)', 'password')
        ->expectsQuestion('Confirm password', 'password')
        ->expectsOutput('The password field must be at least 12 characters.')
        ->assertFailed();

    $this->assertDatabaseMissing('users', ['username' => 'mhrmo-admin']);
});
