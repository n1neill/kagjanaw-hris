<?php

use App\Enums\AuditEvent;
use App\Enums\Finger;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use App\Models\User;

it('lists the enrolled fingers on the employee record for HR', function () {
    $hrOfficer = User::factory()->hrOfficer()->create(['name' => 'Maria Santos']);
    $employee = Employee::factory()->create(['biometric_user_id' => '1024']);
    FingerprintTemplate::factory()->for($employee)->create(['finger' => Finger::LeftIndex, 'enrolled_by' => $hrOfficer->id]);

    $this->actingAs($hrOfficer)->get(route('employees.show', $employee))
        ->assertSee('Left index finger')
        ->assertSee('by Maria Santos');
});

it('does not show fingerprints to the employee on their own record', function () {
    $employee = Employee::factory()->withAccount()->create(['biometric_user_id' => '1024']);
    FingerprintTemplate::factory()->for($employee)->create(['finger' => Finger::LeftIndex]);

    $this->actingAs($employee->user)->get(route('my-record'))
        ->assertOk()
        ->assertDontSee('Left index finger');
});

it('removes an enrolled finger and audits it', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employee = Employee::factory()->create(['biometric_user_id' => '1024']);
    FingerprintTemplate::factory()->for($employee)->create(['finger' => Finger::LeftIndex]);
    FingerprintTemplate::factory()->for($employee)->create(['finger' => Finger::RightIndex]);

    $this->actingAs($hrOfficer)->delete(route('employees.fingerprints.destroy', [$employee, Finger::LeftIndex]))
        ->assertRedirect(route('employees.show', $employee).'#fingerprints')
        ->assertSessionHas('status', 'Left index finger was removed. The kiosk stops accepting it within five minutes.');

    expect($employee->fingerprintTemplates()->pluck('finger')->all())->toBe([Finger::RightIndex]);
    $entry = AuditLog::query()->where('event', AuditEvent::Deleted)->sole();
    expect($entry->user_id)->toBe($hrOfficer->id)
        ->and($entry->description)->toBe('Left index finger of employee '.$employee->employee_number);
});

it('answers 404 for a finger that is not enrolled', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employee = Employee::factory()->create();

    $this->actingAs($hrOfficer)->delete(route('employees.fingerprints.destroy', [$employee, Finger::LeftIndex]))->assertNotFound();
    $this->actingAs($hrOfficer)->delete('/employees/'.$employee->id.'/fingerprints/sixth-finger')->assertNotFound();
});
