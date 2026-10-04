<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an active system administrator', function () {
    $this->artisan('app:create-administrator', ['--name' => 'MHRMO Admin', '--email' => 'mhrmo@cagdianao.gov.ph'])
        ->expectsQuestion('Password (at least 12 characters with upper and lower case letters, a number and a symbol)', 'Adm1n-Passw0rd!')
        ->expectsQuestion('Confirm password', 'Adm1n-Passw0rd!')
        ->expectsOutput('System Administrator account created for mhrmo@cagdianao.gov.ph.')
        ->assertSuccessful();

    $admin = User::where('email', 'mhrmo@cagdianao.gov.ph')->sole();
    expect($admin->name)->toBe('MHRMO Admin')
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->primaryRole())->toBe(Role::SystemAdministrator)
        ->and(Hash::check('Adm1n-Passw0rd!', $admin->password))->toBeTrue();
});

it('refuses a weak password and creates no account', function () {
    $this->artisan('app:create-administrator', ['--name' => 'MHRMO Admin', '--email' => 'mhrmo@cagdianao.gov.ph'])
        ->expectsQuestion('Password (at least 12 characters with upper and lower case letters, a number and a symbol)', 'password')
        ->expectsQuestion('Confirm password', 'password')
        ->expectsOutput('The password field must be at least 12 characters.')
        ->assertFailed();

    $this->assertDatabaseMissing('users', ['email' => 'mhrmo@cagdianao.gov.ph']);
});
