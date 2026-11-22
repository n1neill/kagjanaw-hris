<?php

namespace App\Services\Approvals\Handlers;

use App\Enums\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeServiceRecord;
use App\Models\FingerprintTemplate;
use App\Services\Approvals\ChangeContext;
use App\Services\Attendance\Kiosk\FingerprintEnrollmentService;
use App\Services\EmployeeDocumentService;
use App\Services\EmployeeService;

/**
 * Applies changes to employees, their service record, 201-file documents
 * and enrolled fingerprints.
 */
class EmployeeChanges
{
    public function __construct(
        private EmployeeService $employees,
        private EmployeeDocumentService $documents,
        private FingerprintEnrollmentService $enrollment,
    ) {}

    /**
     * @return array{employee: Employee, temporaryPassword: string}
     */
    public function createEmployee(ChangeContext $change): array
    {
        return $this->employees->create($change->payload);
    }

    public function updateEmployee(ChangeContext $change): Employee
    {
        return $this->employees->update($change->subject(Employee::class), $change->payload);
    }

    public function addServiceRecord(ChangeContext $change): EmployeeServiceRecord
    {
        $entry = $change->subject(Employee::class)->serviceRecords()->make($change->payload);
        $entry->forceFill(['created_by' => $change->author->id])->save();

        return $entry;
    }

    public function updateServiceRecord(ChangeContext $change): EmployeeServiceRecord
    {
        $entry = $change->subject(EmployeeServiceRecord::class);
        $entry->update($change->payload);

        return $entry;
    }

    public function deleteServiceRecord(ChangeContext $change): void
    {
        $change->subject(EmployeeServiceRecord::class)->delete();
    }

    public function uploadEmployeeDocument(ChangeContext $change): EmployeeDocument
    {
        return $this->documents->store(
            $change->subject(Employee::class),
            $change->file('file'),
            DocumentType::from($change->input('document_type')),
            $change->input('title'),
            $change->author,
        );
    }

    public function deleteEmployeeDocument(ChangeContext $change): void
    {
        $this->documents->delete($change->subject(EmployeeDocument::class));
    }

    public function removeFingerprint(ChangeContext $change): void
    {
        $fingerprint = $change->subject(FingerprintTemplate::class);

        $this->enrollment->remove($fingerprint->employee()->firstOrFail(), $fingerprint);
    }
}
