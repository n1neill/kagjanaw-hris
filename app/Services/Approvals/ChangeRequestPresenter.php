<?php

namespace App\Services\Approvals;

use App\Models\AllowanceType;
use App\Models\ChangeRequest;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\LeaveType;
use App\Models\Position;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Turns a change request's payload into rows an HR Officer can read: each
 * field with its proposed value and, for an edit, the record's current value.
 */
class ChangeRequestPresenter
{
    /**
     * Foreign keys shown by the referenced record's name.
     *
     * @var array<string, array{0: class-string<Model>, 1: string}>
     */
    private const REFERENCES = [
        'department_id' => [Department::class, 'name'],
        'position_id' => [Position::class, 'title'],
        'employment_type_id' => [EmploymentType::class, 'name'],
        'allowance_type_id' => [AllowanceType::class, 'name'],
        'leave_type_id' => [LeaveType::class, 'name'],
    ];

    /**
     * @return list<array{label: string, current: ?string, proposed: string, changed: bool}>
     */
    public function rows(ChangeRequest $change): array
    {
        $subject = $change->action->isUpdate() ? $change->subject : null;
        $rows = [];

        foreach ($change->payload as $key => $value) {
            $proposed = $this->format($key, $value);
            $current = $subject !== null && array_key_exists($key, $subject->getAttributes())
                ? $this->format($key, $subject->getAttribute($key))
                : null;

            $rows[] = [
                'label' => Str::headline((string) $key),
                'current' => $current,
                'proposed' => $proposed,
                'changed' => $subject !== null && $current !== $proposed,
            ];
        }

        return $rows;
    }

    private function format(string $key, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        if (isset(self::REFERENCES[$key]) && is_numeric($value)) {
            [$model, $column] = self::REFERENCES[$key];

            return (string) ($model::query()->whereKey($value)->value($column) ?? '#'.$value);
        }

        return match (true) {
            is_bool($value) => $value ? __('Yes') : __('No'),
            $value instanceof BackedEnum => method_exists($value, 'label') ? $value->label() : (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_array($value) => $this->formatArray($value),
            default => (string) $value,
        };
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function formatArray(array $value): string
    {
        $isFlat = collect($value)->every(fn (mixed $item): bool => ! is_array($item));

        if ($isFlat && array_is_list($value)) {
            return implode(', ', array_map(fn (mixed $item): string => $this->format('', $item), $value));
        }

        if ($isFlat) {
            return collect($value)
                ->map(fn (mixed $item, string|int $key): string => Str::headline((string) $key).': '.$this->format((string) $key, $item))
                ->implode('; ');
        }

        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
