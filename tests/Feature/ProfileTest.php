<?php

use App\Models\Employee;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->employee()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->employee()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->employee()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('an employee can update their email but not the name from their HR record', function () {
    $employee = Employee::factory()->withAccount()->create();
    $user = $employee->user;
    $originalName = $user->name;

    $this->actingAs($user)
        ->patch('/profile', ['email' => 'new@cagdianao.local'])
        ->assertSessionHasNoErrors();

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => 'Different Name',
        'email' => 'new@cagdianao.local',
    ]);

    $response->assertSessionHasErrors(['name' => 'The name field is prohibited.']);
    $user->refresh();
    expect($user->email)->toBe('new@cagdianao.local')
        ->and($user->name)->toBe($originalName);
});

test('the email address can be removed', function () {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => '',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBeNull();
});

test('users can not delete their own account', function () {
    $user = User::factory()->employee()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response->assertMethodNotAllowed();
    $this->assertModelExists($user);
});

test('users can not change their own role or status through the profile form', function () {
    $user = User::factory()->employee()->create();

    $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => false,
            'role' => 'system-administrator',
        ]);

    $user->refresh();
    expect($user->is_active)->toBeTrue();
    expect($user->hasRole('system-administrator'))->toBeFalse();
});
