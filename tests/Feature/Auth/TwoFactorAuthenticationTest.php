<?php

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    config(['auth.two_factor.enabled' => true]);
});

/**
 * The code in the most recent sign-in email sent to the user.
 */
function latestSignInCode(User $user): string
{
    $code = '';

    Notification::assertSentTo($user, TwoFactorCodeNotification::class, function (TwoFactorCodeNotification $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    return $code;
}

/**
 * A six-digit code that is not the given one.
 */
function differentCode(string $code): string
{
    return $code === '000000' ? '111111' : '000000';
}

test('a correct password for an account with an email asks for the emailed code instead of signing in', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->post('/login', ['username' => $user->username, 'password' => 'password']);

    $this->assertGuest();
    $response->assertRedirect(route('two-factor.challenge'));
    Notification::assertSentTo($user, TwoFactorCodeNotification::class, fn (TwoFactorCodeNotification $notification): bool => preg_match('/^\d{6}$/', $notification->code) === 1);
});

test('the code page names the masked email address the code was sent to', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'coroco112@gmail.com']);
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);

    $this->get(route('two-factor.challenge'))
        ->assertOk()
        ->assertSee('c********@gmail.com')
        ->assertDontSee('coroco112@gmail.com');
});

test('entering the emailed code signs the user in and records the login time', function () {
    Notification::fake();
    $this->freezeTime();
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);

    $response = $this->post(route('two-factor.verify'), ['code' => latestSignInCode($user)]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    expect($user->refresh()->last_login_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('a code typed with a space is accepted', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);
    $code = latestSignInCode($user);

    $this->post(route('two-factor.verify'), ['code' => substr($code, 0, 3).' '.substr($code, 3)]);

    $this->assertAuthenticatedAs($user);
});

test('an incorrect code does not sign the user in', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);

    $response = $this->from(route('two-factor.challenge'))
        ->post(route('two-factor.verify'), ['code' => differentCode(latestSignInCode($user))]);

    $this->assertGuest();
    $response->assertRedirect(route('two-factor.challenge'))
        ->assertSessionHasErrors(['code' => 'The code is incorrect.']);
});

test('an incorrect code is recorded as a failed sign-in, and only a correct one as a sign-in', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);
    $code = latestSignInCode($user);

    $this->post(route('two-factor.verify'), ['code' => differentCode($code)]);

    expect(AuditLog::query()->where('event', AuditEvent::LoginFailed)->sole()->auditable_id)->toBe($user->id)
        ->and(AuditLog::query()->where('event', AuditEvent::LoggedIn)->exists())->toBeFalse();

    $this->post(route('two-factor.verify'), ['code' => $code]);

    expect(AuditLog::query()->where('event', AuditEvent::LoggedIn)->sole()->auditable_id)->toBe($user->id);
});

test('an expired code does not sign the user in', function () {
    Notification::fake();
    $this->freezeTime();
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);
    $code = latestSignInCode($user);

    $this->travel(11)->minutes();
    $response = $this->post(route('two-factor.verify'), ['code' => $code]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['code' => 'This code has expired. Send a new code and try again.']);
});

test('five incorrect codes end the pending sign-in', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);
    $code = latestSignInCode($user);

    foreach (range(1, 4) as $attempt) {
        $this->post(route('two-factor.verify'), ['code' => differentCode($code)]);
    }
    $response = $this->post(route('two-factor.verify'), ['code' => differentCode($code)]);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['username' => 'Too many incorrect codes. Please sign in again.']);

    $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('login'));
    $this->assertGuest();
});

test('sending a new code replaces the old one', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);
    $firstCode = latestSignInCode($user);

    $this->post(route('two-factor.resend'))
        ->assertSessionHas('status', 'A new code has been sent to your email.');
    $newCode = latestSignInCode($user);

    Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 2);
    $this->post(route('two-factor.verify'), ['code' => $firstCode === $newCode ? differentCode($newCode) : $firstCode]);
    $this->assertGuest();

    $this->post(route('two-factor.verify'), ['code' => $newCode]);
    $this->assertAuthenticatedAs($user);
});

test('remember me ticked on the sign-in form is kept through the code step', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'password', 'remember' => 'on']);

    $response = $this->post(route('two-factor.verify'), ['code' => latestSignInCode($user)]);

    $response->assertCookie(Auth::guard('web')->getRecallerName());
});

test('an account without an email address signs in with its password alone', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => null]);

    $response = $this->post('/login', ['username' => $user->username, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    Notification::assertNothingSent();
});

test('with two-factor sign-in turned off an account with an email signs in with its password alone', function () {
    config(['auth.two_factor.enabled' => false]);
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/login', ['username' => $user->username, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
    Notification::assertNothingSent();
});

test('a wrong password sends no code', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/login', ['username' => $user->username, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['username' => trans('auth.failed')]);

    Notification::assertNothingSent();
});

test('a mail server failure shows an error on the sign-in form instead of crashing', function () {
    $user = User::factory()->create();
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('Connection could not be established'));

    $response = $this->from('/login')->post('/login', ['username' => $user->username, 'password' => 'password']);

    $this->assertGuest();
    $response->assertRedirect('/login')
        ->assertSessionHasErrors(['username' => 'Your sign-in code could not be emailed right now. Please try again later or contact the System Administrator.']);
    $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
});

test('the code page sends a visitor with no pending sign-in back to the sign-in form', function () {
    $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
});

test('an account deactivated while waiting for the code cannot finish signing in', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/login', ['username' => $user->username, 'password' => 'password']);
    $code = latestSignInCode($user);
    $user->forceFill(['is_active' => false])->save();

    $response = $this->post(route('two-factor.verify'), ['code' => $code]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});
