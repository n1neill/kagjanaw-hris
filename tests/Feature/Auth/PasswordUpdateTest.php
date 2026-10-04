<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'N3w-Strong-Passw0rd!',
            'password_confirmation' => 'N3w-Strong-Passw0rd!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('N3w-Strong-Passw0rd!', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'N3w-Strong-Passw0rd!',
            'password_confirmation' => 'N3w-Strong-Passw0rd!',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});

test('a weak new password is rejected', function (string $weakPassword, string $expectedMessage) {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => $weakPassword,
            'password_confirmation' => $weakPassword,
        ]);

    $response->assertSessionHasErrorsIn('updatePassword', ['password' => $expectedMessage]);
    $this->assertTrue(Hash::check('password', $user->refresh()->password));
})->with([
    'shorter than 12 characters' => ['Sh0rt-Pass!', 'The password field must be at least 12 characters.'],
    'without mixed case' => ['all-lowercase-123!', 'The password field must contain at least one uppercase and one lowercase letter.'],
    'without a number' => ['No-Numbers-Here!', 'The password field must contain at least one number.'],
    'without a symbol' => ['NoSymbolsHere123', 'The password field must contain at least one symbol.'],
]);
