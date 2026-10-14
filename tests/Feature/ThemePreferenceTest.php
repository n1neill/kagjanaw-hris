<?php

use App\Enums\ThemePreference;
use App\Models\User;

test('new accounts follow the colour scheme of their computer', function () {
    $user = User::factory()->employee()->create();

    expect($user->refresh()->theme)->toBe(ThemePreference::System);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-theme="system"', false);
});

test('a user can choose a colour scheme and returns to the page they were on', function (ThemePreference $theme) {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->from(route('announcements.index'))
        ->patch(route('profile.theme'), ['theme' => $theme->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('announcements.index'));

    expect($user->refresh()->theme)->toBe($theme);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee('data-theme="'.$theme->value.'"', false);
})->with([ThemePreference::Light, ThemePreference::Dark]);

test('an unknown colour scheme is rejected', function () {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->patch(route('profile.theme'), ['theme' => 'neon'])
        ->assertSessionHasErrors('theme');

    expect($user->refresh()->theme)->toBe(ThemePreference::System);
});

test('guests are sent to sign in before choosing a colour scheme', function () {
    $this->patch(route('profile.theme'), ['theme' => 'dark'])
        ->assertRedirect(route('login'));
});

test('the sign-in page follows the colour scheme of the computer', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-theme="system"', false);
});
