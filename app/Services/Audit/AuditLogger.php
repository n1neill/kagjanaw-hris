<?php

namespace App\Services\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes the audit log: changes to records (through the Auditable model
 * trait), access to personal data, sign-ins and denied requests.
 *
 * Values of hidden or encrypted attributes (passwords, government ID
 * numbers) are never written, only the fact that they changed (RA 10173).
 */
class AuditLogger
{
    public const REDACTED = '[redacted]';

    /**
     * Attributes never recorded for any model.
     *
     * @var list<string>
     */
    private const ALWAYS_EXCLUDED = ['id', 'created_at', 'updated_at', 'remember_token'];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        AuditEvent $event,
        string $description,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null,
    ): AuditLog {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'user_id' => ($actor ?? Auth::user())?->getKey(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() === null ? null : Str::limit($request->userAgent(), 250),
        ]);
    }

    /**
     * Record that someone opened or downloaded personal data. Employees
     * looking at their own records are not logged.
     */
    public function accessed(AuditEvent $event, string $description, ?Model $subject = null, ?Employee $owner = null): void
    {
        if ($owner?->user_id !== null && $owner->user_id === Auth::id()) {
            return;
        }

        $this->record($event, $description, $subject);
    }

    public function modelCreated(Model $model): void
    {
        $this->record(AuditEvent::Created, $this->label($model), $model, newValues: $this->values($model, $model->getAttributes()));
    }

    public function modelUpdated(Model $model): void
    {
        $newValues = $this->values($model, $model->getChanges());

        if ($newValues === []) {
            return;
        }

        $oldValues = $this->values($model, array_intersect_key($model->getRawOriginal(), $newValues));

        $this->record(AuditEvent::Updated, $this->label($model), $model, $oldValues, $newValues);
    }

    public function modelDeleted(Model $model): void
    {
        $this->record(AuditEvent::Deleted, $this->label($model), $model, oldValues: $this->values($model, $model->getRawOriginal()));
    }

    private function label(Model $model): string
    {
        return method_exists($model, 'auditLabel') ? $model->auditLabel() : class_basename($model).' #'.$model->getKey();
    }

    /**
     * Raw attribute values worth recording, with secrets redacted.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function values(Model $model, array $attributes): array
    {
        $excluded = [...self::ALWAYS_EXCLUDED, ...(method_exists($model, 'auditExcluded') ? $model->auditExcluded() : [])];
        $casts = $model->getCasts();
        $values = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            $isSecret = in_array($key, $model->getHidden(), true)
                || in_array($casts[$key] ?? null, ['encrypted', 'hashed'], true);

            $values[$key] = $isSecret && $value !== null ? self::REDACTED : $value;
        }

        return $values;
    }
}
