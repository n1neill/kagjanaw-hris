<?php

namespace App\Services\Approvals;

use App\Enums\ChangeAction;
use App\Enums\ChangeRequestStatus;
use App\Enums\Role;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Notifications\ChangeRequestNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The single entry point for changes to HR records (maker-checker).
 *
 * A user who holds the action's permission through their role applies the
 * change at once. A user who holds it only as a direct grant from the System
 * Administrator submits it instead: nothing is saved to the records until
 * an HR Officer approves it, at which point the same handler applies it.
 */
class ChangeRequestService
{
    /**
     * Apply the change now, or queue it for approval when the actor's
     * permission was granted rather than given by their role.
     *
     * @param  array<string, mixed>  $payload  Validated input; must not contain uploaded files.
     * @param  array<string, UploadedFile>  $files
     */
    public function perform(User $actor, ChangeAction $action, array $payload, ?Model $subject = null, array $files = []): ChangeOutcome
    {
        $payload = $this->normalize($payload);

        if (! $actor->needsApprovalFor($action->permission())) {
            return ChangeOutcome::applied($this->apply($action, $payload, $subject, $files, $actor));
        }

        return ChangeOutcome::queued($this->submit($actor, $action, $payload, $subject, $files));
    }

    /**
     * Apply a pending change on an HR Officer's approval.
     *
     * @throws ChangeCannotBeApplied when the change no longer fits the records; it stays pending.
     */
    public function approve(ChangeRequest $change, User $reviewer, ?string $remarks): mixed
    {
        $this->ensureReviewable($change, $reviewer);
        $change->loadMissing(['requester', 'subject']);

        try {
            $result = DB::transaction(function () use ($change, $reviewer, $remarks): mixed {
                $this->lockPending($change);

                $result = $this->apply(
                    $change->action,
                    $change->payload,
                    $change->subject,
                    $this->storedFiles($change),
                    $change->requester,
                );

                $change->forceFill([
                    'status' => ChangeRequestStatus::Approved,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                    'review_remarks' => $remarks,
                    'failure_message' => null,
                ])->save();

                return $result;
            });
        } catch (ValidationException|ChangeCannotBeApplied $exception) {
            $message = $exception instanceof ValidationException
                ? implode(' ', Arr::flatten($exception->errors()))
                : $exception->getMessage();

            ChangeRequest::query()->whereKey($change->id)->pending()->update(['failure_message' => Str::limit($message, 1000)]);

            throw new ChangeCannotBeApplied($message, previous: $exception);
        }

        $this->deleteStoredFiles($change);
        $change->requester->notify(new ChangeRequestNotification($change, ChangeRequestStatus::Approved));

        return $result;
    }

    public function reject(ChangeRequest $change, User $reviewer, string $remarks): ChangeRequest
    {
        $this->ensureReviewable($change, $reviewer);
        $change->loadMissing('requester');

        DB::transaction(function () use ($change, $reviewer, $remarks): void {
            $this->lockPending($change);

            $change->forceFill([
                'status' => ChangeRequestStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ])->save();
        });

        $this->deleteStoredFiles($change);
        $change->requester->notify(new ChangeRequestNotification($change, ChangeRequestStatus::Rejected));

        return $change;
    }

    /**
     * The submitter withdraws a change still waiting for approval.
     */
    public function cancel(ChangeRequest $change, User $user): ChangeRequest
    {
        if (! $change->isRequestedBy($user)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($change): void {
            $this->lockPending($change);

            $change->forceFill(['status' => ChangeRequestStatus::Cancelled])->save();
        });

        $this->deleteStoredFiles($change);

        return $change;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, UploadedFile>  $files
     */
    private function apply(ChangeAction $action, array $payload, ?Model $subject, array $files, User $author): mixed
    {
        $handler = app($action->handler());

        return $handler->{$action->method()}(new ChangeContext($payload, $subject, $files, $author));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, UploadedFile>  $files
     */
    private function submit(User $actor, ChangeAction $action, array $payload, ?Model $subject, array $files): ChangeRequest
    {
        $storedFiles = [];

        foreach ($files as $key => $file) {
            $storedFiles[$key] = [
                'path' => $file->store('pending-changes', ChangeRequest::DISK),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
            ];
        }

        $change = new ChangeRequest([
            'action' => $action,
            'description' => Str::limit($this->describe($action, $payload, $subject), 250),
            'payload' => $payload,
            'files' => $storedFiles === [] ? null : $storedFiles,
            'status' => ChangeRequestStatus::Pending,
            'requested_by' => $actor->id,
        ]);
        $change->subject()->associate($subject);
        $change->save();
        $change->setRelation('requester', $actor);

        Notification::send($this->approvers($actor), new ChangeRequestNotification($change, ChangeRequestStatus::Pending));

        return $change;
    }

    /**
     * Active HR Officers, other than the submitter.
     *
     * @return Collection<int, User>
     */
    private function approvers(User $submitter): Collection
    {
        return User::role(Role::HrOfficer->value)->active()->whereKeyNot($submitter->id)->get();
    }

    private function describe(ChangeAction $action, array $payload, ?Model $subject): string
    {
        $target = match (true) {
            $subject !== null && method_exists($subject, 'auditLabel') => $subject->auditLabel(),
            isset($payload['first_name'], $payload['last_name']) => trim($payload['first_name'].' '.$payload['last_name']),
            isset($payload['name']) && is_string($payload['name']) => $payload['name'],
            isset($payload['title']) && is_string($payload['title']) => $payload['title'],
            isset($payload['effective_from']) && is_string($payload['effective_from']) => __('effective :date', ['date' => $payload['effective_from']]),
            isset($payload['month']) && is_string($payload['month']) => $payload['month'],
            default => null,
        };

        return $target === null ? __($action->label()) : __($action->label()).': '.$target;
    }

    private function ensureReviewable(ChangeRequest $change, User $reviewer): void
    {
        if ($change->isRequestedBy($reviewer)) {
            throw new AuthorizationException(__('You cannot review a change you submitted.'));
        }
    }

    private function lockPending(ChangeRequest $change): void
    {
        $status = ChangeRequest::query()->lockForUpdate()->whereKey($change->id)->toBase()->value('status');

        if ($status !== ChangeRequestStatus::Pending->value) {
            throw new ChangeCannotBeApplied(__('This change was already decided.'));
        }
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function storedFiles(ChangeRequest $change): array
    {
        $disk = Storage::disk(ChangeRequest::DISK);
        $files = [];

        foreach ($change->files ?? [] as $key => $file) {
            if ($disk->exists($file['path'])) {
                $files[$key] = new UploadedFile($disk->path($file['path']), $file['name'], $file['mime'], test: true);
            }
        }

        return $files;
    }

    private function deleteStoredFiles(ChangeRequest $change): void
    {
        $paths = array_column($change->files ?? [], 'path');

        if ($paths !== []) {
            Storage::disk(ChangeRequest::DISK)->delete($paths);
        }
    }

    /**
     * Reduce the input to plain JSON values (enums to their value, dates to
     * strings), so a change applies the same way now or after approval.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        return json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }
}
