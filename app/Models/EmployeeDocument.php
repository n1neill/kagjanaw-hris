<?php

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\EmployeeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file in an employee's 201 file, stored on the private local disk under
 * employees/{employee_id}/ and served only through an authorized route.
 */
#[Fillable(['document_type', 'title', 'original_name', 'path', 'mime_type', 'size', 'uploaded_by'])]
#[Hidden(['path'])]
class EmployeeDocument extends Model
{
    /** @use HasFactory<EmployeeDocumentFactory> */
    use HasFactory;

    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Whether the browser can display the file inline (PDF or image).
     */
    public function isViewableInline(): bool
    {
        return in_array($this->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }
}
