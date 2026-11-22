<?php

namespace App\Services\Approvals;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * What a handler needs to apply one change: the validated input (as plain
 * JSON-safe values), the record it concerns, any uploaded files, and the
 * user who made the change (the submitter, even when applied on approval).
 */
final readonly class ChangeContext
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, UploadedFile>  $files
     */
    public function __construct(
        public array $payload,
        public ?Model $subject,
        public array $files,
        public User $author,
    ) {}

    public function input(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * The subject, checked to be of the expected class.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @return TModel
     */
    public function subject(string $class): Model
    {
        if (! $this->subject instanceof $class) {
            throw new ChangeCannotBeApplied(__('The record this change concerns no longer exists.'));
        }

        return $this->subject;
    }

    public function file(string $key): UploadedFile
    {
        return $this->files[$key] ?? throw new ChangeCannotBeApplied(__('The file submitted with this change is missing.'));
    }
}
