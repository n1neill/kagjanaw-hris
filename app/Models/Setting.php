<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A runtime setting stored as JSON under a key. Read and write settings
 * through a typed wrapper (e.g. App\Services\Payroll\PayrollSettings).
 */
#[Fillable(['key', 'value', 'updated_by'])]
class Setting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function write(string $key, mixed $value, ?User $user = null): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $user?->id]);
    }
}
