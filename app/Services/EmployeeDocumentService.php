<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stores and removes 201-file documents on the private disk.
 * Files are saved under employees/{employee_id}/ with a random name.
 */
class EmployeeDocumentService
{
    public function store(Employee $employee, UploadedFile $file, DocumentType $type, ?string $title, User $uploader): EmployeeDocument
    {
        $path = $file->store("employees/{$employee->id}", EmployeeDocument::DISK);

        try {
            return $employee->documents()->create([
                'document_type' => $type,
                'title' => filled($title) ? $title : $type->label(),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $uploader->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk(EmployeeDocument::DISK)->delete($path);

            throw $exception;
        }
    }

    public function delete(EmployeeDocument $document): void
    {
        $document->delete();

        Storage::disk(EmployeeDocument::DISK)->delete($document->path);
    }
}
