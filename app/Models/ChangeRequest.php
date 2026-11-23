<?php

namespace App\Models;

use App\Enums\ChangeAction;
use App\Enums\ChangeRequestStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A change submitted through a granted (not role-based) permission, waiting
 * for an HR Officer's approval before it is applied to the records.
 *
 * @property array<string, mixed> $payload
 * @property array<string, array{path: string, name: string, mime: ?string}>|null $files
 */
#[Fillable(['action', 'description', 'payload', 'files', 'status', 'requested_by'])]
class ChangeRequest extends Model
{
    /** @use HasFactory<ChangeRequestFactory> */
    use Auditable, HasFactory;

    /**
     * Where files uploaded with a pending change are kept until it is decided.
     */
    public const DISK = 'uploads';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ChangeAction::class,
            'status' => ChangeRequestStatus::class,
            'payload' => 'encrypted:array',
            'files' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function auditLabel(): string
    {
        return __('Change request #:id: :description', ['id' => $this->id, 'description' => $this->description]);
    }

    /**
     * @return list<string>
     */
    public function auditExcluded(): array
    {
        return ['payload', 'files'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The existing record the change edits or deletes (or adds to).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === ChangeRequestStatus::Pending;
    }

    public function isRequestedBy(User $user): bool
    {
        return $this->requested_by === $user->id;
    }

    /**
     * @param  Builder<ChangeRequest>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', ChangeRequestStatus::Pending);
    }
}
