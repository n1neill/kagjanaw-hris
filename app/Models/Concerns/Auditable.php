<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every create, update and delete of the model in the audit log.
 * Models may override auditLabel() and auditExcluded().
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => app(AuditLogger::class)->modelCreated($model));
        static::updated(fn (Model $model) => app(AuditLogger::class)->modelUpdated($model));
        static::deleted(fn (Model $model) => app(AuditLogger::class)->modelDeleted($model));
    }

    /**
     * How the record is named in the audit log.
     */
    public function auditLabel(): string
    {
        $attributes = $this->getAttributes();

        return (string) ($attributes['name'] ?? $attributes['title'] ?? class_basename($this).' #'.$this->getKey());
    }

    /**
     * Attributes left out of the audit log, e.g. values the system updates on its own.
     *
     * @return list<string>
     */
    public function auditExcluded(): array
    {
        return [];
    }
}
