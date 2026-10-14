<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Models\Concerns\Auditable;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An announcement from the HR Office, for everyone or for chosen offices.
 */
#[Fillable(['title', 'body', 'audience', 'is_pinned', 'published_at', 'expires_on', 'created_by'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(today());
    }

    /**
     * Published and not yet expired.
     *
     * @param  Builder<Announcement>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()->toDateString()));
    }

    /**
     * Current announcements addressed to the user: those for everyone, and
     * those for the office of the user's employee record.
     *
     * @param  Builder<Announcement>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $departmentId = $user->employee?->department_id;

        $query->current()->where(fn (Builder $query) => $query
            ->where('audience', AnnouncementAudience::Everyone)
            ->when($departmentId, fn (Builder $query) => $query->orWhereHas('departments', fn (Builder $departments) => $departments->whereKey($departmentId))));
    }

    /**
     * Pinned first, then newest.
     *
     * @param  Builder<Announcement>  $query
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id');
    }
}
