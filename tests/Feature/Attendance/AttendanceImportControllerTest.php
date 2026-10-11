<?php

use App\Enums\AttendanceLogStatus;
use App\Enums\AttendanceSource;
use App\Enums\DtrStatus;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 18:00:00'));
});

function attendanceCsv(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('punches.csv', $contents);
}

it('imports valid rows, reports bad rows and builds the DTR', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    $employee = Employee::factory()->create(['biometric_user_id' => '1024', 'date_hired' => '2020-01-06']);

    $response = $this->actingAs($hrOfficer)->post(route('attendance.import.store'), [
        'file' => attendanceCsv(implode("\n", [
            'biometric_user_id,date,time',
            '1024,2026-10-06,07:58',
            '1024,2026-10-06,12:02',
            '1024,2026-10-06,12:55',
            '1024,2026-10-06,17:05',
            '1024,06/10/2026,08:00',
            '8888,2026-10-06,08:00',
        ])),
    ]);

    $response->assertRedirect(route('attendance.import.create'));
    expect(session('importResult'))->toMatchArray([
        'rows' => 6,
        'imported' => 5,
        'duplicates' => 0,
        'flagged' => 1,
        'errors' => ['Row 6: The date field must match the format Y-m-d.'],
    ]);
    $this->assertDatabaseHas('attendance_logs', ['biometric_user_id' => '1024', 'logged_at' => '2026-10-06 07:58:00', 'source' => AttendanceSource::CsvImport->value]);
    $this->assertDatabaseHas('attendance_logs', ['biometric_user_id' => '8888', 'status' => AttendanceLogStatus::UnknownUser->value]);
    expect($employee->dailyTimeRecords()->sole()->status)->toBe(DtrStatus::Present);
});

it('skips punches already imported', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    Employee::factory()->create(['biometric_user_id' => '1024', 'date_hired' => '2020-01-06']);
    $csv = "biometric_user_id,date,time\n1024,2026-10-06,07:58\n";

    $this->actingAs($hrOfficer)->post(route('attendance.import.store'), ['file' => attendanceCsv($csv)]);
    $this->actingAs($hrOfficer)->post(route('attendance.import.store'), ['file' => attendanceCsv($csv)]);

    expect(session('importResult'))->toMatchArray(['imported' => 0, 'duplicates' => 1]);
    $this->assertDatabaseCount('attendance_logs', 1);
});

it('accepts columns in any order and ignores extra columns', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();
    Employee::factory()->create(['biometric_user_id' => '1024', 'date_hired' => '2020-01-06']);

    $this->actingAs($hrOfficer)->post(route('attendance.import.store'), [
        'file' => attendanceCsv("Time,Name,Date,Biometric_User_ID\n07:58,Juan,2026-10-06,1024\n"),
    ]);

    expect(session('importResult'))->toMatchArray(['imported' => 1, 'errors' => []]);
});

it('explains a missing header', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();

    $this->actingAs($hrOfficer)->post(route('attendance.import.store'), [
        'file' => attendanceCsv("1024,2026-10-06,07:58\n"),
    ]);

    expect(session('importResult')['errors'])->toBe(['The first row must be the header: biometric_user_id,date,time.']);
    $this->assertDatabaseCount('attendance_logs', 0);
});

it('rejects files that are not CSV', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();

    $response = $this->actingAs($hrOfficer)->post(route('attendance.import.store'), [
        'file' => UploadedFile::fake()->create('punches.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
    ]);

    $response->assertSessionHasErrors(['file' => 'Upload a CSV file.']);
});

it('offers the template for download', function () {
    $hrOfficer = User::factory()->hrOfficer()->create();

    $response = $this->actingAs($hrOfficer)->get(route('attendance.import.template'));

    $response->assertDownload('attendance-import-template.csv');
    expect($response->streamedContent())->toStartWith("biometric_user_id,date,time\n1024,2026-10-05,07:58\n");
});

it('forbids department heads from importing', function () {
    $head = User::factory()->departmentHead()->create();

    $response = $this->actingAs($head)->post(route('attendance.import.store'), [
        'file' => attendanceCsv("biometric_user_id,date,time\n1024,2026-10-06,07:58\n"),
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('attendance_logs', 0);
});
