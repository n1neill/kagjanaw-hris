<?php

use App\Enums\DtrStatus;
use App\Enums\Role;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));
});

describe('correct a day', function () {
    it('saves the corrected punches, recomputes the day and records the audit trail', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
        $record = DailyTimeRecord::factory()->for($employee)->missingPunch()->create(['work_date' => '2026-10-06']);

        $response = $this->actingAs($hrOfficer)->put(route('attendance.dtr.update', [$employee, '2026-10-06']), [
            'am_in' => '07:55',
            'am_out' => '12:01',
            'pm_in' => '12:55',
            'pm_out' => '17:00',
            'reason' => 'Forgot to punch out; confirmed by department head.',
        ]);

        $response->assertRedirect(route('attendance.dtr.show', [$employee, 'month' => '2026-10']));
        $record->refresh();
        expect($record->pm_out)->toBe('17:00:00')
            ->and($record->status)->toBe(DtrStatus::Present)
            ->and($record->absent_minutes)->toBe(0)
            ->and($record->is_adjusted)->toBeTrue();

        $adjustment = $record->adjustments()->sole();
        expect($adjustment->user_id)->toBe($hrOfficer->id)
            ->and($adjustment->changes)->toBe(['pm_out' => ['from' => null, 'to' => '17:00']])
            ->and($adjustment->reason)->toBe('Forgot to punch out; confirmed by department head.');
    });

    it('creates the record for a day that was never processed', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);

        $this->actingAs($hrOfficer)->put(route('attendance.dtr.update', [$employee, '2026-10-05']), [
            'am_in' => '08:30',
            'am_out' => '12:00',
            'pm_in' => '13:00',
            'pm_out' => '17:00',
            'reason' => 'Device was offline; times from the logbook.',
        ])->assertSessionHasNoErrors();

        $record = $employee->dailyTimeRecords()->sole();
        expect($record->late_minutes)->toBe(30)
            ->and($record->is_adjusted)->toBeTrue();
    });

    it('keeps the correction when the month is recomputed from logs', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
        $this->actingAs($hrOfficer)->put(route('attendance.dtr.update', [$employee, '2026-10-06']), [
            'am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00',
            'reason' => 'Attended a seminar at the provincial capitol (travel order 2026-114).',
        ]);

        $this->actingAs($hrOfficer)->post(route('attendance.dtr.reprocess', [$employee, 'month' => '2026-10']));

        expect($employee->dailyTimeRecords()->whereDate('work_date', '2026-10-06')->sole()->status)->toBe(DtrStatus::Present)
            ->and($employee->dailyTimeRecords()->whereDate('work_date', '2026-10-05')->sole()->status)->toBe(DtrStatus::Absent);
    });

    it('requires a reason', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);

        $response = $this->actingAs($hrOfficer)->put(route('attendance.dtr.update', [$employee, '2026-10-06']), [
            'am_in' => '08:00',
            'reason' => '',
        ]);

        $response->assertSessionHasErrors(['reason' => 'The reason field is required.']);
        expect($employee->dailyTimeRecords()->count())->toBe(0);
    });

    it('rejects punches out of order', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);

        $response = $this->actingAs($hrOfficer)->put(route('attendance.dtr.update', [$employee, '2026-10-06']), [
            'am_in' => '08:00',
            'am_out' => '07:30',
            'reason' => 'Entering times from the logbook.',
        ]);

        $response->assertSessionHasErrors(['am_out' => 'This punch must be later than the one before it.']);
    });

    it('rejects a submission that changes nothing', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
        DailyTimeRecord::factory()->for($employee)->create(['work_date' => '2026-10-06']);

        $response = $this->actingAs($hrOfficer)->put(route('attendance.dtr.update', [$employee, '2026-10-06']), [
            'am_in' => '07:55', 'am_out' => '12:01', 'pm_in' => '12:55', 'pm_out' => '17:02',
            'reason' => 'Checking the record.',
        ]);

        $response->assertSessionHasErrors(['am_in' => 'No punches were changed.']);
    });

    it('cannot correct a future date or a date before hiring', function (string $date) {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2026-10-01']);

        $this->actingAs($hrOfficer)->get(route('attendance.dtr.edit', [$employee, $date]))->assertNotFound();
    })->with(['future' => '2026-10-08', 'before hiring' => '2026-09-30']);

    it('forbids department heads and employees from correcting', function (Role $role) {
        $employee = Employee::factory()->withAccount($role)->create(['date_hired' => '2020-01-06']);

        $response = $this->actingAs($employee->user)->put(route('attendance.dtr.update', [$employee, '2026-10-06']), [
            'am_in' => '08:00', 'am_out' => '12:00', 'pm_in' => '13:00', 'pm_out' => '17:00',
            'reason' => 'I was actually on time.',
        ]);

        $response->assertForbidden();
        expect($employee->dailyTimeRecords()->count())->toBe(0);
    })->with(['department head' => Role::DepartmentHead, 'employee' => Role::Employee]);
});

describe('validate a month', function () {
    it('marks the month\'s finished days as validated by HR', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
        DailyTimeRecord::factory()->for($employee)->create(['work_date' => '2026-10-05']);
        DailyTimeRecord::factory()->for($employee)->absent()->create(['work_date' => '2026-10-06']);
        DailyTimeRecord::factory()->for($employee)->create(['work_date' => '2026-10-07', 'status' => DtrStatus::Pending]);

        $response = $this->actingAs($hrOfficer)->post(route('attendance.dtr.validate', [$employee, 'month' => '2026-10']));

        $response->assertSessionHas('status', '2 days of October 2026 validated.');
        expect($employee->dailyTimeRecords()->whereNotNull('validated_at')->pluck('validated_by')->all())->toBe([$hrOfficer->id, $hrOfficer->id]);
    });

    it('refuses while a day still has a missing punch', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
        DailyTimeRecord::factory()->for($employee)->missingPunch()->create(['work_date' => '2026-10-06']);

        $response = $this->actingAs($hrOfficer)->post(route('attendance.dtr.validate', [$employee, 'month' => '2026-10']));

        $response->assertSessionHasErrors(['month' => 'Correct the days with a missing punch before validating: Oct 6.']);
        expect($employee->dailyTimeRecords()->whereNotNull('validated_at')->count())->toBe(0);
    });

    it('requires validation again after a correction', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create(['date_hired' => '2020-01-06']);
        DailyTimeRecord::factory()->for($employee)->create(['work_date' => '2026-10-06']);
        $this->actingAs($hrOfficer)->post(route('attendance.dtr.validate', [$employee, 'month' => '2026-10']));

        $this->actingAs($hrOfficer)->put(route('attendance.dtr.update', [$employee, '2026-10-06']), [
            'am_in' => '08:40', 'am_out' => '12:01', 'pm_in' => '12:55', 'pm_out' => '17:02',
            'reason' => 'Arrival corrected per guard logbook.',
        ]);

        expect($employee->dailyTimeRecords()->sole()->validated_at)->toBeNull();
    });
});

it('recomputes the month from logs for HR only', function () {
    $employee = Employee::factory()->withAccount()->create();

    $this->actingAs($employee->user)->post(route('attendance.dtr.reprocess', [$employee, 'month' => '2026-10']))->assertForbidden();
});

it('fills in absences from the hire date up to today when recomputing', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employee = Employee::factory()->create(['date_hired' => '2026-10-05']);

    $this->actingAs($hrOfficer)->post(route('attendance.dtr.reprocess', [$employee, 'month' => '2026-10']))
        ->assertSessionHas('status', 'The DTR for October 2026 was recomputed from the attendance logs.');

    expect($employee->dailyTimeRecords()->orderBy('work_date')->pluck('status')->map->value->all())->toBe(['absent', 'absent', 'absent']);
});
