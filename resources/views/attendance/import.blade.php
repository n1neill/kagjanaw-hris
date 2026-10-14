<x-app-layout :title="__('Import Attendance')">
    <x-slot name="header">{{ __('Import Attendance') }}</x-slot>

    @if ($result = session('importResult'))
        <x-card :title="__('Import results — :file', ['file' => $result['filename']])">
            <dl class="grid gap-4 sm:grid-cols-4">
                <x-stat-card :label="__('Rows read')" :value="$result['rows']" />
                <x-stat-card :label="__('New punches saved')" :value="$result['imported']" />
                <x-stat-card :label="__('Already stored (skipped)')" :value="$result['duplicates']" />
                <x-stat-card :label="__('Flagged (unknown ID or future time)')" :value="$result['flagged']" />
            </dl>

            @if ($result['errors'] !== [])
                <div class="mt-4 rounded-lg border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 p-4 text-sm text-red-800 dark:text-red-200">
                    <p class="font-semibold">{{ trans_choice(':count row was skipped:|:count rows were skipped:', count($result['errors']), ['count' => count($result['errors'])]) }}</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach (array_slice($result['errors'], 0, 50) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    @if (count($result['errors']) > 50)
                        <p class="mt-2">{{ __('…and :count more.', ['count' => count($result['errors']) - 50]) }}</p>
                    @endif
                </div>
            @endif

            @if ($result['flagged'] > 0)
                <p class="mt-4 text-sm text-ink-muted">
                    <a href="{{ route('attendance.logs', ['status' => 'unknown-user']) }}" class="font-medium text-brand-700 dark:text-brand-400 hover:underline">{{ __('Review flagged punches') }}</a>
                </p>
            @endif
        </x-card>
    @endif

    <x-card :title="__('Upload a CSV file')" :description="__('Use this when a device cannot be reached over the network, or to enter punches from a paper logbook.')">
        <x-slot name="actions">
            <x-button-link :href="route('attendance.import.template')" variant="secondary">{{ __('Download template') }}</x-button-link>
        </x-slot>

        <div class="mb-6 rounded-lg bg-surface-muted p-4 text-sm text-ink">
            <p class="font-semibold">{{ __('File format') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>{{ __('The first row is the header:') }} <code class="font-mono">{{ implode(',', $columns) }}</code></li>
                <li>{{ __('One punch per row. The biometric ID is the user number on the time clock, as entered on the employee record.') }}</li>
                <li>{{ __('Date as YYYY-MM-DD and time in 24-hour HH:MM, e.g.') }} <code class="font-mono">1024,2026-10-05,07:58</code></li>
                <li>{{ __('Up to :max rows per file. Punches already in the system are skipped, so re-uploading a file is safe.', ['max' => number_format($maxRows)]) }}</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('attendance.import.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4">
            @csrf
            <div>
                <x-input-label for="file" :value="__('CSV file (up to 5 MB)')" />
                <input id="file" name="file" type="file" accept=".csv,text/csv" required class="mt-1 block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 dark:file:bg-brand-500/10 dark:file:text-brand-300 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100 dark:hover:file:bg-brand-500/20">
                <x-input-error :messages="$errors->get('file')" class="mt-2" />
            </div>
            <x-primary-button>{{ __('Import') }}</x-primary-button>
        </form>
    </x-card>
</x-app-layout>
