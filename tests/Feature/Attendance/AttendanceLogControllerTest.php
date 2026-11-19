<?php

use App\Enums\AuditEvent;
use App\Models\AttendanceLog;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;

it('lists flagged punches when filtered by status', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    AttendanceLog::factory()->unknownUser('4321')->create();
    AttendanceLog::factory()->for(Employee::factory()->state(['biometric_user_id' => '1024']))->create(['biometric_user_id' => '1024']);

    $response = $this->actingAs($hrOfficer)->get(route('attendance.logs', ['status' => 'unknown-user']));

    $response->assertSee('4321')
        ->assertSee('1 punch is flagged and left out of the DTRs.')
        ->assertDontSee('>1024<', escape: false);
});

it('forbids department heads', function () {
    $head = User::factory()->departmentHead()->create();

    $this->actingAs($head)->get(route('attendance.logs'))->assertForbidden();
});

it('clears the punches matching the filters and records it in the audit log', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    AttendanceLog::factory()->unknownUser('4321')->count(2)->create();
    AttendanceLog::factory()->create();

    $this->actingAs($hrOfficer)->delete(route('attendance.logs.clear'), ['status' => 'unknown-user'])
        ->assertRedirect(route('attendance.logs'))
        ->assertSessionHas('status');

    expect(AttendanceLog::count())->toBe(1);

    $audit = AuditLog::query()->latest('id')->first();
    expect($audit->event)->toBe(AuditEvent::Deleted)
        ->and($audit->user_id)->toBe($hrOfficer->id)
        ->and($audit->description)->toBe('Cleared 2 attendance logs')
        ->and($audit->old_values['count'])->toBe(2)
        ->and($audit->old_values['filters'])->toBe(['status' => 'unknown-user']);
});

it('clears every punch when no filter is set', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    AttendanceLog::factory()->count(3)->create();

    $this->actingAs($hrOfficer)->delete(route('attendance.logs.clear'))->assertRedirect();

    expect(AttendanceLog::count())->toBe(0)
        ->and(AuditLog::query()->latest('id')->first()->old_values['count'])->toBe(3);
});

it('does not let department heads clear punches', function () {
    $head = User::factory()->departmentHead()->create();
    AttendanceLog::factory()->create();

    $this->actingAs($head)->delete(route('attendance.logs.clear'))->assertForbidden();

    expect(AttendanceLog::count())->toBe(1);
});
