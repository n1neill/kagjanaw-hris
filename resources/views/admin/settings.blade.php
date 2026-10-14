<x-app-layout :title="__('System Settings')">
    <x-slot name="header">{{ __('System Settings') }}</x-slot>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ __('Please correct the highlighted fields below.') }}
            </div>
        @endif

        <x-card :title="__('Biometric synchronization')" :description="__('How often the scheduler pulls new punches from every active time clock.')">
            <div class="max-w-xs">
                <x-input-label for="sync_interval_minutes" :value="__('Sync every (minutes)')" />
                <x-text-input id="sync_interval_minutes" name="sync_interval_minutes" type="number" min="1" max="59" class="mt-1" :value="old('sync_interval_minutes', $syncIntervalMinutes)" required />
                <x-input-error :messages="$errors->get('sync_interval_minutes')" class="mt-2" />
            </div>
        </x-card>

        <x-card :title="__('Backups')" :description="__('A backup holds the whole database and every uploaded file (201 documents, leave attachments).')">
            <div class="space-y-5">
                <x-checkbox name="backup_enabled" value="1" :checked="old('backup_enabled', $backupEnabled)" :label="__('Back up automatically every day')" />

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="backup_time" :value="__('Time of the daily backup')" />
                        <x-text-input id="backup_time" name="backup_time" type="time" class="mt-1" :value="old('backup_time', $backupTime)" required aria-describedby="backup_time-hint" />
                        <p id="backup_time-hint" class="mt-1 text-xs text-ink-muted">{{ __('The server must be on at this time. Choose a time after office hours.') }}</p>
                        <x-input-error :messages="$errors->get('backup_time')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="backups_kept" :value="__('Backups to keep')" />
                        <x-text-input id="backups_kept" name="backups_kept" type="number" min="3" max="365" class="mt-1" :value="old('backups_kept', $backupsKept)" required aria-describedby="backups_kept-hint" />
                        <p id="backups_kept-hint" class="mt-1 text-xs text-ink-muted">{{ __('Older backups are deleted after each new backup. Copy important ones to external storage.') }}</p>
                        <x-input-error :messages="$errors->get('backups_kept')" class="mt-2" />
                    </div>
                </div>
            </div>
        </x-card>

        <x-card :title="__('Audit log')" :description="__('Entries older than this are deleted every night.')">
            <div class="max-w-xs">
                <x-input-label for="audit_retention_years" :value="__('Keep entries for (years)')" />
                <x-text-input id="audit_retention_years" name="audit_retention_years" type="number" min="1" max="20" class="mt-1" :value="old('audit_retention_years', $auditRetentionYears)" required />
                <x-input-error :messages="$errors->get('audit_retention_years')" class="mt-2" />
            </div>
        </x-card>

        <p class="text-sm text-ink-muted">
            {{ __('Office hours, the grace period, holidays and payroll settings are kept by the HR Office under Work Calendar and Payroll.') }}
        </p>

        <x-primary-button>{{ __('Save Settings') }}</x-primary-button>
    </form>
</x-app-layout>
