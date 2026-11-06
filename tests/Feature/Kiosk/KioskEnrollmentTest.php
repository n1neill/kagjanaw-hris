<?php

use App\Enums\AuditEvent;
use App\Enums\EmploymentStatus;
use App\Enums\Finger;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->kiosk = BiometricDevice::factory()->kiosk()->create();
    $this->withToken('test-kiosk-token');
});

/**
 * Sign an HR officer in at the kiosk and return the enrollment session token.
 */
function signInAtKiosk(User $user): string
{
    return test()->postJson(route('kiosk.enrollment-sessions.store'), [
        'username' => $user->username,
        'password' => 'password',
    ])->assertCreated()->json('token');
}

describe('signing in', function () {
    it('opens an enrollment session for HR', function () {
        $hr = User::factory()->hrOfficer()->create(['name' => 'Maria Santos']);

        $this->postJson(route('kiosk.enrollment-sessions.store'), ['username' => $hr->username, 'password' => 'password'])
            ->assertCreated()
            ->assertJsonPath('user.name', 'Maria Santos')
            ->assertJsonStructure(['token', 'expires_at']);
    });

    it('refuses a wrong password and audits the attempt', function () {
        $hr = User::factory()->hrOfficer()->create();

        $this->postJson(route('kiosk.enrollment-sessions.store'), ['username' => $hr->username, 'password' => 'not-it'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'These credentials do not match our records.']);

        $entry = AuditLog::query()->where('event', AuditEvent::LoginFailed)->sole();
        expect($entry->description)->toBe('Kiosk sign-in: '.$hr->auditLabel())
            ->and($entry->auditable_id)->toBe($hr->id);
    });

    it('refuses staff who may not enroll fingerprints', function (Role $role) {
        $user = User::factory()->withRole($role)->create();

        $this->postJson(route('kiosk.enrollment-sessions.store'), ['username' => $user->username, 'password' => 'password'])
            ->assertJsonValidationErrors(['username' => 'Only HR staff can enroll fingerprints.']);

        expect(AuditLog::query()->where('event', AuditEvent::AccessDenied)->sole()->auditable_id)->toBe($user->id);
    })->with([
        'system administrator' => Role::SystemAdministrator,
        'department head' => Role::DepartmentHead,
        'employee' => Role::Employee,
    ]);

    it('refuses a deactivated HR account', function () {
        $hr = User::factory()->hrOfficer()->inactive()->create();

        $this->postJson(route('kiosk.enrollment-sessions.store'), ['username' => $hr->username, 'password' => 'password'])
            ->assertJsonValidationErrors(['username' => 'Your account has been deactivated. Please contact the System Administrator.']);
    });

    it('locks the username out after five failures', function () {
        $this->freezeTime();
        $hr = User::factory()->hrOfficer()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('kiosk.enrollment-sessions.store'), ['username' => $hr->username, 'password' => 'wrong']);
        }

        $this->postJson(route('kiosk.enrollment-sessions.store'), ['username' => $hr->username, 'password' => 'password'])
            ->assertJsonValidationErrors(['username' => 'Too many login attempts. Please try again in 60 seconds.']);
    });
});

describe('enrolling', function () {
    it('lists employees in service with a biometric ID and their enrolled fingers', function () {
        $session = signInAtKiosk(User::factory()->hrOfficer()->create());
        $enrolled = Employee::factory()->create(['biometric_user_id' => '1024', 'last_name' => 'Abad']);
        FingerprintTemplate::factory()->for($enrolled)->create(['finger' => Finger::LeftIndex]);
        Employee::factory()->create(['biometric_user_id' => '2048', 'employment_status' => EmploymentStatus::Resigned]);
        Employee::factory()->create(['biometric_user_id' => null]);

        $response = $this->withHeader('X-Enrollment-Token', $session)->getJson(route('kiosk.employees'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enrolled->id)
            ->assertJsonPath('data.0.biometric_user_id', '1024')
            ->assertJsonPath('data.0.enrolled_fingers', ['left-index']);
    });

    it('stores an enrolled finger encrypted and audits who enrolled it', function () {
        $hr = User::factory()->hrOfficer()->create();
        $session = signInAtKiosk($hr);
        $employee = Employee::factory()->create(['biometric_user_id' => '1024']);

        $this->withHeader('X-Enrollment-Token', $session)
            ->putJson(route('kiosk.fingerprints.update', [$employee, Finger::RightIndex]), ['template' => 'QUJDRA==', 'quality' => 87])
            ->assertCreated()
            ->assertJson(['finger' => 'right-index', 'enrolled_fingers' => ['right-index']]);

        $fingerprint = $employee->fingerprintTemplates()->sole();
        expect($fingerprint->template)->toBe('QUJDRA==')
            ->and($fingerprint->quality)->toBe(87)
            ->and($fingerprint->enrolled_by)->toBe($hr->id)
            ->and($fingerprint->biometric_device_id)->toBe($this->kiosk->id)
            ->and(DB::table('fingerprint_templates')->value('template'))->not->toContain('QUJDRA');

        $entry = AuditLog::query()->where('auditable_type', $fingerprint->getMorphClass())->sole();
        expect($entry->user_id)->toBe($hr->id)
            ->and($entry->new_values['template'])->toBe(AuditLogger::REDACTED);
    });

    it('replaces a finger enrolled earlier', function () {
        $session = signInAtKiosk(User::factory()->hrOfficer()->create());
        $employee = Employee::factory()->create(['biometric_user_id' => '1024']);
        FingerprintTemplate::factory()->for($employee)->create(['finger' => Finger::RightIndex, 'template' => 'T0xE']);

        $this->withHeader('X-Enrollment-Token', $session)
            ->putJson(route('kiosk.fingerprints.update', [$employee, Finger::RightIndex]), ['template' => 'TkVX'])
            ->assertOk();

        expect($employee->fingerprintTemplates()->sole()->template)->toBe('TkVX');
    });

    it('allows at most two fingers per employee', function () {
        $session = signInAtKiosk(User::factory()->hrOfficer()->create());
        $employee = Employee::factory()->create(['biometric_user_id' => '1024', 'first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Cruz', 'name_extension' => null]);
        FingerprintTemplate::factory()->for($employee)->create(['finger' => Finger::RightIndex]);
        FingerprintTemplate::factory()->for($employee)->create(['finger' => Finger::LeftIndex]);

        $this->withHeader('X-Enrollment-Token', $session)
            ->putJson(route('kiosk.fingerprints.update', [$employee, Finger::RightThumb]), ['template' => 'QUJDRA=='])
            ->assertJsonValidationErrors(['finger' => 'Juan Cruz already has 2 fingers enrolled. Remove one in the HRIS first.']);

        expect($employee->fingerprintTemplates()->count())->toBe(2);
    });

    it('refuses an employee who is separated or has no biometric ID', function (array $attributes) {
        $session = signInAtKiosk(User::factory()->hrOfficer()->create());
        $employee = Employee::factory()->create($attributes);

        $this->withHeader('X-Enrollment-Token', $session)
            ->putJson(route('kiosk.fingerprints.update', [$employee, Finger::RightIndex]), ['template' => 'QUJDRA=='])
            ->assertJsonValidationErrors(['employee']);

        $this->assertDatabaseCount('fingerprint_templates', 0);
    })->with([
        'separated' => [['biometric_user_id' => '1024', 'employment_status' => EmploymentStatus::Retired]],
        'no biometric ID' => [['biometric_user_id' => null]],
    ]);

    it('rejects a template that is not base64', function () {
        $session = signInAtKiosk(User::factory()->hrOfficer()->create());
        $employee = Employee::factory()->create(['biometric_user_id' => '1024']);

        $this->withHeader('X-Enrollment-Token', $session)
            ->putJson(route('kiosk.fingerprints.update', [$employee, Finger::RightIndex]), ['template' => '<script>'])
            ->assertJsonValidationErrors(['template' => 'The template field format is invalid.']);
    });

    it('requires a current HR sign-in at this kiosk', function (string $case) {
        $hr = User::factory()->hrOfficer()->create();
        $session = signInAtKiosk($hr);
        $employee = Employee::factory()->create(['biometric_user_id' => '1024']);

        if ($case === 'expired') {
            $this->travel(16)->minutes();
        } elseif ($case === 'signed out') {
            $this->withHeader('X-Enrollment-Token', $session)->deleteJson(route('kiosk.enrollment-sessions.destroy'))->assertNoContent();
        } elseif ($case === 'deactivated') {
            $hr->update(['is_active' => false]);
        } else {
            BiometricDevice::factory()->kiosk('second-kiosk-token')->create();
            $this->withToken('second-kiosk-token');
        }

        $this->withHeader('X-Enrollment-Token', $session)
            ->putJson(route('kiosk.fingerprints.update', [$employee, Finger::RightIndex]), ['template' => 'QUJDRA=='])
            ->assertUnauthorized();

        $this->assertDatabaseCount('fingerprint_templates', 0);
    })->with(['expired', 'signed out', 'deactivated', 'other kiosk']);
});

describe('template feed', function () {
    it('sends the enrolled fingers of employees in service', function () {
        $employee = Employee::factory()->create(['biometric_user_id' => '1024', 'first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Cruz', 'name_extension' => null]);
        FingerprintTemplate::factory()->for($employee)->create(['finger' => Finger::RightIndex, 'template' => 'QUJDRA==']);
        $separated = Employee::factory()->create(['biometric_user_id' => '2048', 'employment_status' => EmploymentStatus::Resigned]);
        FingerprintTemplate::factory()->for($separated)->create();

        $this->getJson(route('kiosk.templates'))
            ->assertOk()
            ->assertExactJson(['data' => [[
                'employee_id' => $employee->id,
                'biometric_user_id' => '1024',
                'employee_name' => 'Juan Cruz',
                'finger' => 'right-index',
                'template' => 'QUJDRA==',
            ]]]);
    });

    it('answers 304 when the list has not changed', function () {
        FingerprintTemplate::factory()->for(Employee::factory()->create(['biometric_user_id' => '1024']))->create();

        $etag = $this->getJson(route('kiosk.templates'))->assertOk()->headers->get('ETag');

        $this->withHeader('If-None-Match', $etag)->getJson(route('kiosk.templates'))->assertNotModified();
    });
});
