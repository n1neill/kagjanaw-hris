<?php

namespace App\Http\Controllers\Employees;

use App\Enums\AuditEvent;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\Audit\AuditLogger;
use App\Services\EmployeeDocumentService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, view, download and remove 201-file documents. Files live on the
 * private disk and are only ever streamed through these authorized actions.
 */
class EmployeeDocumentController extends Controller
{
    /**
     * Headers that stop browsers and proxies from sniffing or caching personal files.
     *
     * @var array<string, string>
     */
    private const PRIVATE_FILE_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store, max-age=0',
    ];

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee, EmployeeDocumentService $documents): RedirectResponse
    {
        $document = $documents->store(
            $employee,
            $request->file('file'),
            DocumentType::from($request->validated('document_type')),
            $request->validated('title'),
            $request->user(),
        );

        return redirect()
            ->to(route('employees.show', $employee).'#documents')
            ->with('status', __(':title was uploaded.', ['title' => $document->title]));
    }

    /**
     * Open a PDF or image in the browser.
     */
    public function show(Employee $employee, EmployeeDocument $document, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('viewDocuments', $employee);
        abort_unless($document->isViewableInline(), 404);

        $audit->accessed(AuditEvent::Viewed, $document->auditLabel(), $document, $employee);

        return $this->diskContaining($document)->response($document->path, $document->original_name, [
            ...self::PRIVATE_FILE_HEADERS,
            'Content-Type' => $document->mime_type,
        ], 'inline');
    }

    public function download(Employee $employee, EmployeeDocument $document, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('viewDocuments', $employee);

        $audit->accessed(AuditEvent::Downloaded, $document->auditLabel(), $document, $employee);

        return $this->diskContaining($document)->download($document->path, $document->original_name, self::PRIVATE_FILE_HEADERS);
    }

    public function destroy(Employee $employee, EmployeeDocument $document, EmployeeDocumentService $documents): RedirectResponse
    {
        Gate::authorize('manageDocuments', $employee);

        $documents->delete($document);

        return redirect()
            ->to(route('employees.show', $employee).'#documents')
            ->with('status', __(':title was deleted.', ['title' => $document->title]));
    }

    /**
     * The private disk, after confirming the document's file is still there.
     */
    private function diskContaining(EmployeeDocument $document): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(EmployeeDocument::DISK);

        abort_unless($disk->exists($document->path), 404);

        return $disk;
    }
}
