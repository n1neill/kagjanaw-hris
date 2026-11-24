<?php

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('uploads');

    $this->employee = Employee::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Andres',
        'last_name' => 'Dela Cruz',
        'name_extension' => null,
        'employee_number' => '2024-0001',
    ]);
});

it('moves documents and leave attachments from numbered folders into the employee folder under their uploaded names', function () {
    $document = EmployeeDocument::factory()->for($this->employee)->create([
        'path' => "employees/{$this->employee->id}/Xk29fa.pdf",
        'original_name' => 'appointment.pdf',
    ]);
    $leaveRequest = LeaveRequest::factory()->for($this->employee)->create();
    $leaveRequest->forceFill(['attachment_path' => "leave-requests/{$this->employee->id}/P0q8z.pdf", 'attachment_name' => 'medical-certificate.pdf'])->save();
    Storage::disk('uploads')->put($document->path, 'appointment');
    Storage::disk('uploads')->put($leaveRequest->attachment_path, 'certificate');

    $this->artisan('files:organize')->expectsOutput('2 files moved.')->assertSuccessful();

    $documentPath = 'employees/Dela Cruz, Juan A. (2024-0001)/appointment.pdf';
    $attachmentPath = 'leave-requests/Dela Cruz, Juan A. (2024-0001)/medical-certificate.pdf';
    expect($document->fresh()->path)->toBe($documentPath)
        ->and($leaveRequest->fresh()->attachment_path)->toBe($attachmentPath)
        ->and(Storage::disk('uploads')->get($documentPath))->toBe('appointment')
        ->and(Storage::disk('uploads')->get($attachmentPath))->toBe('certificate')
        ->and(Storage::disk('uploads')->exists("employees/{$this->employee->id}"))->toBeFalse();
});

it('numbers files that would share a name and leaves files already in place alone', function () {
    $folder = 'employees/Dela Cruz, Juan A. (2024-0001)';
    $inPlace = EmployeeDocument::factory()->for($this->employee)->create(['path' => "{$folder}/scan.pdf", 'original_name' => 'scan.pdf']);
    $numbered = EmployeeDocument::factory()->for($this->employee)->create(['path' => "employees/{$this->employee->id}/a1.pdf", 'original_name' => 'scan.pdf']);
    Storage::disk('uploads')->put($inPlace->path, 'first');
    Storage::disk('uploads')->put($numbered->path, 'second');

    $this->artisan('files:organize')->expectsOutput('1 file moved.')->assertSuccessful();

    expect($inPlace->fresh()->path)->toBe("{$folder}/scan.pdf")
        ->and($numbered->fresh()->path)->toBe("{$folder}/scan (2).pdf")
        ->and(Storage::disk('uploads')->get("{$folder}/scan.pdf"))->toBe('first')
        ->and(Storage::disk('uploads')->get("{$folder}/scan (2).pdf"))->toBe('second');
});

it('changes nothing on a dry run', function () {
    $document = EmployeeDocument::factory()->for($this->employee)->create(['path' => "employees/{$this->employee->id}/Xk29fa.pdf", 'original_name' => 'appointment.pdf']);
    Storage::disk('uploads')->put($document->path, 'appointment');

    $this->artisan('files:organize --dry-run')->expectsOutput('1 file would be moved.')->assertSuccessful();

    expect($document->fresh()->path)->toBe("employees/{$this->employee->id}/Xk29fa.pdf");
    Storage::disk('uploads')->assertExists($document->path);
});

it('reports a file missing from storage and leaves its record unchanged', function () {
    $document = EmployeeDocument::factory()->for($this->employee)->create(['path' => "employees/{$this->employee->id}/gone.pdf"]);

    $this->artisan('files:organize')->expectsOutput('1 file could not be moved.')->assertFailed();

    expect($document->fresh()->path)->toBe("employees/{$this->employee->id}/gone.pdf");
});
