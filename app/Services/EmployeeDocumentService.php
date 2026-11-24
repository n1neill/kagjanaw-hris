<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores and removes 201-file documents on the private disk, in the
 * employee's folder under "employees/" (see EmployeeFileNames).
 */
class EmployeeDocumentService
{
    public function __construct(private EmployeeFileNames $fileNames) {}

    public function store(Employee $employee, UploadedFile $file, DocumentType $type, ?string $title, User $uploader): EmployeeDocument
    {
        $folder = $this->fileNames->folder('employees', $employee);
        $fileName = $this->fileNames->availableFileName(EmployeeDocument::DISK, $folder, $file->getClientOriginalName());
        $path = $file->storeAs($folder, $fileName, EmployeeDocument::DISK);

        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('The file could not be saved. Try again.')]);
        }

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
