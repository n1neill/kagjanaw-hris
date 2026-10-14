<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Support\Facades\Artisan;

describe('changes to records', function () {
    it('records who changed what, with values before and after', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['mobile_number' => '09171234567', 'employee_number' => '2021-0230']);

        $this->actingAs($hrOfficer);
        $employee->update(['mobile_number' => '09181234567']);

        $entry = AuditLog::query()->where('event', AuditEvent::Updated)->whereMorphedTo('auditable', $employee)->sole();
        expect($entry->user_id)->toBe($hrOfficer->id)
            ->and($entry->description)->toContain('(2021-0230)')
            ->and($entry->old_values)->toBe(['mobile_number' => '09171234567'])
            ->and($entry->new_values)->toBe(['mobile_number' => '09181234567']);
    });

    it('never records government ID numbers or passwords', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $employee = Employee::factory()->create(['gsis_number' => '12345678901']);
        $user = User::factory()->employee()->create();

        $this->actingAs($admin);
        $employee->update(['gsis_number' => '99999999999']);
        $user->update(['password' => 'Another-Secret-Pass1']);

        $employeeEntry = AuditLog::query()->where('event', AuditEvent::Updated)->whereMorphedTo('auditable', $employee)->sole();
        $userEntry = AuditLog::query()->where('event', AuditEvent::Updated)->whereMorphedTo('auditable', $user)->sole();
        expect($employeeEntry->old_values)->toBe(['gsis_number' => AuditLogger::REDACTED])
            ->and($employeeEntry->new_values)->toBe(['gsis_number' => AuditLogger::REDACTED])
            ->and($userEntry->new_values)->toBe(['password' => AuditLogger::REDACTED]);

        $created = AuditLog::query()->where('event', AuditEvent::Created)->whereMorphedTo('auditable', $employee)->sole();
        expect($created->new_values['tin'])->toBe(AuditLogger::REDACTED)
            ->and(json_encode($created->new_values))->not->toContain('12345678901');
    });

    it('skips updates that only touch values the system maintains', function () {
        $user = User::factory()->employee()->create();

        $user->forceFill(['last_login_at' => now()])->save();

        expect(AuditLog::query()->where('event', AuditEvent::Updated)->whereMorphedTo('auditable', $user)->exists())->toBeFalse();
    });

    it('records role changes', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $user = User::factory()->employee()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => Role::DepartmentHead->value,
        ])->assertRedirect();

        $entry = AuditLog::query()->where('event', AuditEvent::Updated)->whereMorphedTo('auditable', $user)->latest('id')->first();
        expect($entry->old_values)->toBe(['role' => 'Employee'])
            ->and($entry->new_values)->toBe(['role' => 'Department Head'])
            ->and($entry->user_id)->toBe($admin->id);
    });
});

describe('access to personal data', function () {
    it('records HR opening an employee\'s 201 file', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->withAccount()->create();

        $this->actingAs($hrOfficer)->get(route('employees.show', $employee))->assertOk();

        $entry = AuditLog::query()->where('event', AuditEvent::Viewed)->sole();
        expect($entry->user_id)->toBe($hrOfficer->id)
            ->and($entry->description)->toStartWith('201 file of')
            ->and($entry->auditable_id)->toBe($employee->id);
    });

    it('does not record employees opening their own records', function () {
        $employee = Employee::factory()->withAccount()->create();

        $this->actingAs($employee->user)->get(route('employees.show', $employee))->assertOk();

        expect(AuditLog::query()->where('event', AuditEvent::Viewed)->exists())->toBeFalse();
    });
});

describe('sign-ins and denied access', function () {
    it('records sign-in and sign-out', function () {
        $user = User::factory()->employee()->create();

        $this->post('/login', ['username' => $user->username, 'password' => 'password']);
        $this->post('/logout');

        expect(AuditLog::query()->where('user_id', $user->id)->pluck('event')->all())
            ->toContain(AuditEvent::LoggedIn, AuditEvent::LoggedOut);
    });

    it('records a failed sign-in against the account', function () {
        $user = User::factory()->employee()->create();

        $this->post('/login', ['username' => $user->username, 'password' => 'wrong-password']);

        $entry = AuditLog::query()->where('event', AuditEvent::LoginFailed)->sole();
        expect($entry->user_id)->toBeNull()
            ->and($entry->auditable_id)->toBe($user->id)
            ->and($entry->ip_address)->toBe('127.0.0.1');
    });

    it('does not record an unknown username, which may be a mistyped password', function () {
        $this->post('/login', ['username' => 'My-Secret-Pass1', 'password' => 'whatever']);

        $entry = AuditLog::query()->where('event', AuditEvent::LoginFailed)->sole();
        expect($entry->description)->toBe('Unknown username')
            ->and($entry->auditable_id)->toBeNull();
    });

    it('records lockouts', function () {
        $user = User::factory()->employee()->create();

        foreach (range(1, 6) as $attempt) {
            $this->post('/login', ['username' => $user->username, 'password' => 'wrong-password']);
        }

        expect(AuditLog::query()->where('event', AuditEvent::LockedOut)->sole()->auditable_id)->toBe($user->id);
    });

    it('records refused requests', function () {
        $employee = Employee::factory()->withAccount()->create();

        $this->actingAs($employee->user)->get(route('admin.users.index'))->assertForbidden();

        $entry = AuditLog::query()->where('event', AuditEvent::AccessDenied)->sole();
        expect($entry->user_id)->toBe($employee->user_id)
            ->and($entry->description)->toBe('GET /admin/users');
    });
});

describe('audit log screen', function () {
    it('lists entries for the administrator, filtered by category', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $failed = AuditLog::factory()->create(['event' => AuditEvent::LoginFailed, 'description' => 'Unknown username']);
        $change = AuditLog::factory()->create(['event' => AuditEvent::Updated]);

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index', ['category' => 'security']));

        $response->assertOk();
        expect($response->viewData('entries')->pluck('id'))->toContain($failed->id)->not->toContain($change->id);
    });

    it('shows the values of an entry', function () {
        $admin = User::factory()->systemAdministrator()->create();
        $entry = AuditLog::factory()->create(['old_values' => ['mobile_number' => '09171234567'], 'new_values' => ['mobile_number' => '09181234567']]);

        $this->actingAs($admin)->get(route('admin.audit-logs.show', $entry))
            ->assertOk()
            ->assertSeeInOrder(['mobile_number', '09171234567', '09181234567']);
    });

    it('is only available to the System Administrator', function (Role $role) {
        $user = User::factory()->withRole($role)->create();
        $entry = AuditLog::factory()->create();

        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.audit-logs.show', $entry))->assertForbidden();
    })->with([
        'hr officer' => Role::HrOfficer,
        'department head' => Role::DepartmentHead,
        'employee' => Role::Employee,
    ]);

    it('prunes entries older than the retention period', function () {
        $admin = User::factory()->systemAdministrator()->create();
        app(SystemSettings::class)->update(['sync_interval_minutes' => 5, 'backup_enabled' => true, 'backup_time' => '18:00', 'backups_kept' => 30, 'audit_retention_years' => 2], $admin);
        $old = AuditLog::factory()->create(['created_at' => now()->subYears(2)->subDay()]);
        $recent = AuditLog::factory()->create(['created_at' => now()->subYears(2)->addDay()]);

        Artisan::call('model:prune', ['--model' => [AuditLog::class]]);

        expect(AuditLog::query()->whereKey($old->id)->exists())->toBeFalse()
            ->and(AuditLog::query()->whereKey($recent->id)->exists())->toBeTrue();
    });
});
