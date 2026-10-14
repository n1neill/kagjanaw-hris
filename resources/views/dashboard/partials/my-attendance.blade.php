{{--
    The signed-in employee's attendance today and this month.
    Expects: $myToday (?DailyTimeRecord), $myMonth (?array)
--}}
@use('App\Models\DailyTimeRecord')
@use('App\Services\Attendance\AttendanceReportService', 'Report')

@if ($myMonth !== null)
    <x-card :title="__('My attendance today')" :description="now()->format('l, F j, Y')">
        <x-slot name="actions">
            <x-button-link :href="route('attendance.my')" variant="secondary">{{ __('My Attendance') }}</x-button-link>
        </x-slot>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-line p-4">
                <p class="text-sm font-medium text-ink-muted">{{ __('Morning') }}</p>
                <p class="mt-1 font-mono text-lg text-ink">
                    {{ DailyTimeRecord::displayTime($myToday?->am_in) ?: '—' }} · {{ DailyTimeRecord::displayTime($myToday?->am_out) ?: '—' }}
                </p>
            </div>
            <div class="rounded-lg border border-line p-4">
                <p class="text-sm font-medium text-ink-muted">{{ __('Afternoon') }}</p>
                <p class="mt-1 font-mono text-lg text-ink">
                    {{ DailyTimeRecord::displayTime($myToday?->pm_in) ?: '—' }} · {{ DailyTimeRecord::displayTime($myToday?->pm_out) ?: '—' }}
                </p>
            </div>
            <x-stat-card :label="__('Late this month')" :value="$myMonth['late_count'].'×'" :hint="Report::formatMinutes($myMonth['late_minutes'])" />
            <x-stat-card :label="__('Absences this month')" :value="$myMonth['absent_days']" :hint="__('days')" />
        </div>

        @if ($myToday)
            <p class="mt-4 text-sm text-ink-muted">{{ __('Today:') }} <x-badge :color="$myToday->status->badgeColor()">{{ $myToday->status->label() }}</x-badge>
                @if ($myToday->late_minutes)
                    <span class="ml-2 text-yellow-800 dark:text-yellow-200">{{ __('Late by :time', ['time' => Report::formatMinutes($myToday->late_minutes)]) }}</span>
                @endif
            </p>
        @else
            <p class="mt-4 text-sm text-ink-muted">{{ __('No punches recorded yet today.') }}</p>
        @endif
    </x-card>
@endif
