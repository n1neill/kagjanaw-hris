<?php

use App\Models\User;
use Illuminate\Support\Carbon;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->employee()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('a successful login records the login time', function () {
    Carbon::setTestNow('2026-10-05 08:00:00');
    $user = User::factory()->employee()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($user->refresh()->last_login_at->toDateTimeString())->toBe('2026-10-05 08:00:00');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('deactivated users can not authenticate', function () {
    $user = User::factory()->employee()->inactive()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors([
        'email' => 'Your account has been deactivated. Please contact the System Administrator.',
    ]);
});

test('a deactivated account does not reveal its status without the correct password', function () {
    $user = User::factory()->inactive()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('login is throttled after five failed attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Too many login attempts.');
});

test('a user deactivated during a session is logged out on the next request', function () {
    $user = User::factory()->employee()->create();
    $this->actingAs($user);
    $user->forceFill(['is_active' => false])->save();

    $response = $this->get('/dashboard');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('self-registration is not available', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});
