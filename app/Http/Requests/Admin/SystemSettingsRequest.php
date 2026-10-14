<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * System settings: the device sync interval, the daily backup and the audit
 * log retention period.
 */
class SystemSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageSettings);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['backup_enabled' => $this->boolean('backup_enabled')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sync_interval_minutes' => ['required', 'integer', 'between:1,59'],
            'backup_enabled' => ['required', 'boolean'],
            'backup_time' => ['required', 'date_format:H:i'],
            'backups_kept' => ['required', 'integer', 'between:3,365'],
            'audit_retention_years' => ['required', 'integer', 'between:1,20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sync_interval_minutes' => __('sync interval'),
            'backups_kept' => __('number of backups kept'),
            'audit_retention_years' => __('retention period'),
        ];
    }

    /**
     * @return array{sync_interval_minutes: int, backup_enabled: bool, backup_time: string, backups_kept: int, audit_retention_years: int}
     */
    public function settings(): array
    {
        return [
            'sync_interval_minutes' => (int) $this->validated('sync_interval_minutes'),
            'backup_enabled' => (bool) $this->validated('backup_enabled'),
            'backup_time' => $this->validated('backup_time'),
            'backups_kept' => (int) $this->validated('backups_kept'),
            'audit_retention_years' => (int) $this->validated('audit_retention_years'),
        ];
    }
}
