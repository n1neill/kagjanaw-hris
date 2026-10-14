<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Services\SystemSettings;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * One audit log entry. Written by App\Services\Audit\AuditLogger and never
 * edited; entries older than the retention period are pruned
 * (`php artisan model:prune`, scheduled daily).
 */
#[Fillable([
    'user_id',
    'event',
    'auditable_type',
    'auditable_id',
    'description',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The user who acted; null for the system, scheduled tasks and unknown visitors.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record acted on, if any.
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The record type in words, e.g. "Employee document".
     */
    public function subjectType(): ?string
    {
        return $this->auditable_type === null
            ? null
            : Str::ucfirst(Str::lower(Str::headline(class_basename($this->auditable_type))));
    }

    /**
     * Entries older than the retention period set in System Settings.
     *
     * @return Builder<AuditLog>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subYears(app(SystemSettings::class)->auditRetentionYears()));
    }
}
