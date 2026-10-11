<?php

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(EmployeeDocument::DISK);
});

/**
 * A document row with a matching file on the faked disk.
 */
function storedDocument(Employee $employee, string $contents = '%PDF-1.4 appointment'): EmployeeDocument
{
    $document = EmployeeDocument::factory()->for($employee)->create();
    Storage::disk(EmployeeDocument::DISK)->put($document->path, $contents);

    return $document;
}

describe('store', function () {
    it('stores the upload privately under the employee folder', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrOfficer)->post(route('employees.documents.store', $employee), [
            'document_type' => DocumentType::Appointment->value,
            'title' => 'Original Appointment 2021',
            'file' => UploadedFile::fake()->create('appointment.pdf', 300, 'application/pdf'),
        ]);

        $response->assertRedirect(route('employees.show', $employee).'#documents')
            ->assertSessionHas('status', 'Original Appointment 2021 was uploaded.');
        $document = $employee->documents()->sole();
        expect($document->document_type)->toBe(DocumentType::Appointment)
            ->and($document->original_name)->toBe('appointment.pdf')
            ->and($document->path)->toStartWith("employees/{$employee->id}/")
            ->and($document->uploaded_by)->toBe($hrOfficer->id);
        Storage::disk(EmployeeDocument::DISK)->assertExists($document->path);
    });

    it('titles the document after its type when no title is given', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($hrOfficer)->post(route('employees.documents.store', $employee), [
            'document_type' => DocumentType::NbiClearance->value,
            'file' => UploadedFile::fake()->image('nbi.jpg'),
        ]);

        expect($employee->documents()->sole()->title)->toBe('NBI Clearance');
    });

    it('rejects files that are not PDF, JPG or PNG', function (UploadedFile $file) {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrOfficer)->post(route('employees.documents.store', $employee), [
            'document_type' => DocumentType::Other->value,
            'file' => $file,
        ]);

        $response->assertSessionHasErrors(['file' => 'Upload a PDF, JPG or PNG file.']);
        $this->assertDatabaseCount('employee_documents', 0);
        expect(Storage::disk(EmployeeDocument::DISK)->allFiles())->toBeEmpty();
    })->with([
        'executable' => fn () => UploadedFile::fake()->create('setup.exe', 100, 'application/x-msdownload'),
        'html page' => fn () => UploadedFile::fake()->createWithContent('notes.html', '<script>alert(1)</script>'),
        // A real file (not a fake) so the MIME type is sniffed from its contents, not its name.
        'html page renamed to pdf' => function () {
            $path = tempnam(sys_get_temp_dir(), 'upload');
            file_put_contents($path, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');

            return new UploadedFile($path, 'scan.pdf', null, null, true);
        },
    ]);

    it('rejects files larger than 10 MB', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrOfficer)->post(route('employees.documents.store', $employee), [
            'document_type' => DocumentType::Other->value,
            'file' => UploadedFile::fake()->create('huge.pdf', 10_241, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors(['file' => 'The file may not be larger than 10 MB.']);
        $this->assertDatabaseCount('employee_documents', 0);
    });

    it('forbids employees from uploading to their own 201 file', function () {
        $employee = Employee::factory()->withAccount()->create();

        $response = $this->actingAs($employee->user)->post(route('employees.documents.store', $employee), [
            'document_type' => DocumentType::Other->value,
            'file' => UploadedFile::fake()->create('mine.pdf', 50, 'application/pdf'),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('employee_documents', 0);
    });
});

describe('download', function () {
    it('lets HR officers and the employee download the file', function (string $viewer) {
        $employee = Employee::factory()->withAccount()->create();
        $document = storedDocument($employee);
        $user = $viewer === 'hr officer' ? User::factory()->hrOfficer()->create() : $employee->user;

        $response = $this->actingAs($user)->get(route('employees.documents.download', [$employee, $document]));

        $response->assertOk()
            ->assertDownload($document->original_name)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        expect($response->streamedContent())->toBe('%PDF-1.4 appointment');
    })->with(['hr officer', 'the employee']);

    it('opens PDFs inline for viewing', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();
        $document = storedDocument($employee);

        $response = $this->actingAs($hrOfficer)->get(route('employees.documents.show', [$employee, $document]));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
    });

    it('hides another employee\'s document from an employee who edits the URL', function () {
        $employee = Employee::factory()->withAccount()->create();
        $colleague = Employee::factory()->create();
        $document = storedDocument($colleague);

        $response = $this->actingAs($employee->user)->get(route('employees.documents.download', [$colleague, $document]));

        $response->assertNotFound();
    });

    it('rejects a document requested under a different employee\'s URL', function () {
        $employee = Employee::factory()->withAccount()->create();
        $colleague = Employee::factory()->create();
        $colleaguesDocument = storedDocument($colleague);

        $response = $this->actingAs($employee->user)->get(route('employees.documents.download', [$employee, $colleaguesDocument]));

        $response->assertNotFound();
    });

    it('hides 201 documents from the employee\'s department head', function () {
        $department = Department::factory()->create();
        $head = Employee::factory()->withAccount(Role::DepartmentHead)->create(['department_id' => $department->id]);
        $staff = Employee::factory()->create(['department_id' => $department->id]);
        $document = storedDocument($staff);

        $response = $this->actingAs($head->user)->get(route('employees.documents.download', [$staff, $document]));

        $response->assertNotFound();
    });

    it('responds not found when the file is missing from storage', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();
        $document = EmployeeDocument::factory()->for($employee)->create();

        $response = $this->actingAs($hrOfficer)->get(route('employees.documents.download', [$employee, $document]));

        $response->assertNotFound();
    });
});

describe('destroy', function () {
    it('deletes the record and the stored file', function () {
        $hrOfficer = User::factory()->hrOfficer()->create();
        $employee = Employee::factory()->create();
        $document = storedDocument($employee);

        $response = $this->actingAs($hrOfficer)->delete(route('employees.documents.destroy', [$employee, $document]));

        $response->assertRedirect(route('employees.show', $employee).'#documents');
        $this->assertModelMissing($document);
        Storage::disk(EmployeeDocument::DISK)->assertMissing($document->path);
    });

    it('forbids employees from deleting their own documents', function () {
        $employee = Employee::factory()->withAccount()->create();
        $document = storedDocument($employee);

        $response = $this->actingAs($employee->user)->delete(route('employees.documents.destroy', [$employee, $document]));

        $response->assertForbidden();
        $this->assertModelExists($document);
        Storage::disk(EmployeeDocument::DISK)->assertExists($document->path);
    });
});
