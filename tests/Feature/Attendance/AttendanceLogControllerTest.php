<?php

use App\Models\AttendanceLog;
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
