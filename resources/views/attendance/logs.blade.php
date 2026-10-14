<x-app-layout :title="__('Attendance Logs')">
    <x-slot name="header">{{ __('Attendance Logs') }}</x-slot>

    @if ($flaggedCount > 0)
        <div role="status" class="rounded-lg border border-yellow-300 dark:border-yellow-500/30 bg-yellow-50 dark:bg-yellow-500/10 px-4 py-3 text-sm text-yellow-900 dark:text-yellow-200">
            {{ trans_choice(':count punch is flagged and left out of the DTRs.|:count punches are flagged and left out of the DTRs.', $flaggedCount, ['count' => $flaggedCount]) }}
            {{ __('Unknown IDs are attached automatically once an employee is given that biometric user ID on their record.') }}
            <a href="{{ route('attendance.logs', ['status' => 'unknown-user']) }}" class="font-semibold underline">{{ __('Show unknown IDs') }}</a>
        </div>
    @endif

    <x-card :title="__('Raw punches')" :description="__('Every punch received from the time clocks and CSV imports, newest first.')">
        <form method="GET" action="{{ route('attendance.logs') }}" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-56">
                <x-input-label for="status" :value="__('Status')" />
                <x-select-input id="status" name="status" class="mt-1" :options="$statuses" :selected="$filters['status'] ?? null" :placeholder="__('All')" />
            </div>
            <div>
                <x-input-label for="date" :value="__('Date')" />
                <x-text-input id="date" name="date" type="date" class="mt-1" :value="$filters['date'] ?? null" />
            </div>
            <div>
                <x-input-label for="biometric_user_id" :value="__('Biometric ID')" />
                <x-text-input id="biometric_user_id" name="biometric_user_id" inputmode="numeric" class="mt-1 w-32" :value="$filters['biometric_user_id'] ?? null" />
            </div>
            <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>
            @if ($filters !== [])
                <a href="{{ route('attendance.logs') }}" class="text-sm text-ink-muted hover:underline">{{ __('Clear') }}</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        <th class="py-2 pr-3">{{ __('Time') }}</th>
                        <th class="px-2 py-2">{{ __('Biometric ID') }}</th>
                        <th class="px-2 py-2">{{ __('Employee') }}</th>
                        <th class="px-2 py-2">{{ __('Source') }}</th>
                        <th class="px-2 py-2">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b border-line">
                            <td class="whitespace-nowrap py-2 pr-3 font-mono">{{ $log->logged_at->format('Y-m-d H:i:s') }}</td>
                            <td class="px-2 py-2 font-mono">{{ $log->biometric_user_id }}</td>
                            <td class="px-2 py-2">
                                @if ($log->employee)
                                    <a href="{{ route('attendance.dtr.show', [$log->employee, 'month' => $log->logged_at->format('Y-m')]) }}" class="text-brand-700 dark:text-brand-400 hover:underline">{{ $log->employee->sortable_name }}</a>
                                @else
                                    <span class="text-ink-subtle">—</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-ink-muted">{{ $log->device?->name ?? $log->source->label() }}</td>
                            <td class="px-2 py-2">
                                <x-badge :color="$log->status === \App\Enums\AttendanceLogStatus::Valid ? 'green' : 'yellow'">{{ $log->status->label() }}</x-badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"><x-empty-state icon="clock" :title="__('No punches match these filters.')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </x-card>
</x-app-layout>
