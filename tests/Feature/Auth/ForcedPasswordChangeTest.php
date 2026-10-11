<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('sends a user with a temporary password to the change-password page', function (string $page) {
    $user = User::factory()->employee()->mustChangePassword()->create();

    $response = $this->actingAs($user)->get($page);

    $response->assertRedirect(route('password.change'));
})->with([
    'dashboard' => '/dashboard',
    'profile' => '/profile',
    'own record' => '/my-record',
]);

it('lets a user with a temporary password log out', function () {
    $user = User::factory()->employee()->mustChangePassword()->create();

    $this->actingAs($user)->post('/logout');

    $this->assertGuest();
});

it('saves the new password and clears the requirement', function () {
    $user = User::factory()->employee()->mustChangePassword()->create();

    $response = $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'password',
        'password' => 'My-0wn-Passw0rd!',
        'password_confirmation' => 'My-0wn-Passw0rd!',
    ]);

    $response->assertRedirect(route('dashboard'));
    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('My-0wn-Passw0rd!', $user->password))->toBeTrue();
    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('rejects reusing the temporary password', function () {
    $user = User::factory()->employee()->mustChangePassword()->create(['password' => 'Temp0rary-Pass!']);

    $response = $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'Temp0rary-Pass!',
        'password' => 'Temp0rary-Pass!',
        'password_confirmation' => 'Temp0rary-Pass!',
    ]);

    $response->assertSessionHasErrors(['password' => 'Choose a password different from your temporary password.']);
    expect($user->refresh()->must_change_password)->toBeTrue();
});

it('requires the temporary password', function () {
    $user = User::factory()->employee()->mustChangePassword()->create();

    $response = $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'not-the-password',
        'password' => 'My-0wn-Passw0rd!',
        'password_confirmation' => 'My-0wn-Passw0rd!',
    ]);

    $response->assertSessionHasErrors(['current_password' => 'The password is incorrect.']);
    expect($user->refresh()->must_change_password)->toBeTrue();
});

it('enforces the password policy on the new password', function () {
    $user = User::factory()->employee()->mustChangePassword()->create();

    $response = $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors(['password' => 'The password field must be at least 12 characters.']);
});

it('skips the page for users who already chose their password', function () {
    $user = User::factory()->employee()->create();

    $response = $this->actingAs($user)->get(route('password.change'));

    $response->assertRedirect(route('dashboard'));
});

it('blocks the My Account password form until the temporary password is replaced', function () {
    $user = User::factory()->employee()->mustChangePassword()->create();

    $response = $this->actingAs($user)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'My-0wn-Passw0rd!',
        'password_confirmation' => 'My-0wn-Passw0rd!',
    ]);

    $response->assertRedirect(route('password.change'));
    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

it('clears the requirement when the user resets the password through an emailed link', function () {
    Notification::fake();
    $user = User::factory()->employee()->mustChangePassword()->create();
    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'My-0wn-Passw0rd!',
            'password_confirmation' => 'My-0wn-Passw0rd!',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect($user->refresh()->must_change_password)->toBeFalse();
});
