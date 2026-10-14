<?php

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Records sign-ins, sign-outs, failed attempts, lockouts and password resets
 * in the audit log. A username that matches no account is not recorded, in
 * case someone typed a password into the username field.
 */
class RecordAuthenticationEvents
{
    public function __construct(private AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record(AuditEvent::LoggedIn, $event->user->auditLabel(), $event->user, actor: $event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record(AuditEvent::LoggedOut, $event->user->auditLabel(), $event->user, actor: $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->record(
            AuditEvent::LoginFailed,
            $user === null ? __('Unknown username') : $user->auditLabel(),
            $user,
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $user = User::query()->where('username', (string) $event->request->input('username'))->first();

        $this->audit->record(
            AuditEvent::LockedOut,
            $user === null ? __('Unknown username') : $user->auditLabel(),
            $user,
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record(AuditEvent::PasswordReset, $event->user->auditLabel(), $event->user, actor: $event->user);
        }
    }
}
