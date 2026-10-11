{{--
    A month of Daily Time Records with weekly subtotals.
    Expects: $employee, $days, $weeks, $totals, $canManage (bool)
--}}
@use('App\Models\DailyTimeRecord')
@use('App\Services\Attendance\AttendanceReportService', 'Report')

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="py-2 pr-3">{{ __('Date') }}</th>
                <th class="px-2 py-2 text-center" colspan="2">{{ __('Morning') }}<span class="block font-normal normal-case">{{ __('In · Out') }}</span></th>
                <th class="px-2 py-2 text-center" colspan="2">{{ __('Afternoon') }}<span class="block font-normal normal-case">{{ __('In · Out') }}</span></th>
                <th class="px-2 py-2 text-right">{{ __('Late') }}</th>
                <th class="px-2 py-2 text-right">{{ __('Undertime') }}</th>
                <th class="px-2 py-2">{{ __('Status') }}</th>
                @if ($canManage)
                    <th class="py-2 pl-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($days as $day)
                @php
                    /** @var ?DailyTimeRecord $record */
                    $record = $day['record'];
                    $isOffDay = $day['holiday'] !== null || ($record?->status && ! $record->status->isWorkingDay());
                @endphp
                <tr @class(['border-b border-gray-100', 'bg-gray-50 text-gray-500' => $isOffDay, 'bg-yellow-50/60' => $record?->status === \App\Enums\DtrStatus::Incomplete])>
                    <td class="whitespace-nowrap py-2 pr-3">
                        <span class="font-medium text-gray-900">{{ $day['date']->format('j') }}</span>
                        <span class="text-gray-500">{{ $day['date']->format('D') }}</span>
                    </td>
                    @if ($day['holiday'] && ! $record?->am_in && ! $record?->pm_in)
                        <td colspan="4" class="px-2 py-2 text-center italic">{{ $day['holiday']->name }}</td>
                    @elseif ($day['leave'] && $day['leave']->portion === \App\Enums\LeavePortion::FullDay && ! $record?->am_in && ! $record?->pm_in)
                        <td colspan="4" class="px-2 py-2 text-center italic text-brand-800">{{ $day['leave']->leaveRequest->leaveType->name }}</td>
                    @else
                        <td class="px-2 py-2 text-center font-mono">{{ DailyTimeRecord::displayTime($record?->am_in) }}</td>
                        <td class="px-2 py-2 text-center font-mono">{{ DailyTimeRecord::displayTime($record?->am_out) }}</td>
                        <td class="px-2 py-2 text-center font-mono">{{ DailyTimeRecord::displayTime($record?->pm_in) }}</td>
                        <td class="px-2 py-2 text-center font-mono">{{ DailyTimeRecord::displayTime($record?->pm_out) }}</td>
                    @endif
                    <td class="px-2 py-2 text-right">{{ $record?->late_minutes ? Report::formatMinutes($record->late_minutes) : '' }}</td>
                    <td class="px-2 py-2 text-right">{{ $record?->undertime_minutes ? Report::formatMinutes($record->undertime_minutes) : '' }}</td>
                    <td class="px-2 py-2">
                        @if ($record)
                            <x-badge :color="$record->status->badgeColor()">{{ $record->status->label() }}</x-badge>
                            @if ($record->is_adjusted)
                                <x-badge color="brand" title="{{ __('Corrected by HR') }}">{{ __('Corrected') }}</x-badge>
                            @endif
                            @if ($day['leave'] && $day['leave']->portion !== \App\Enums\LeavePortion::FullDay)
                                <span class="block text-xs text-brand-800">{{ $day['leave']->leaveRequest->leaveType->code }} · {{ mb_strtolower($day['leave']->portion->label()) }}</span>
                            @endif
                        @elseif ($day['date']->isFuture())
                            <span class="text-gray-400">—</span>
                        @else
                            <span class="text-xs text-gray-400">{{ __('Not processed') }}</span>
                        @endif
                    </td>
                    @if ($canManage)
                        <td class="py-2 pl-2 text-right">
                            @if (! $day['date']->isFuture() && $employee->wasEmployedOn($day['date']))
                                <a href="{{ route('attendance.dtr.edit', [$employee, $day['date']->toDateString()]) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">{{ __('Correct') }}</a>
                            @endif
                        </td>
                    @endif
                </tr>

                @if ($day['endsWeek'])
                    @php($week = $weeks[$day['week']])
                    <tr class="border-b-2 border-gray-200 bg-brand-50/40 text-xs text-gray-600">
                        <td class="py-1.5 pr-3 font-semibold">{{ __('Week total') }}</td>
                        <td colspan="4" class="px-2 py-1.5">
                            {{ __(':present present · :absent days absent · :missing missing punch', [
                                'present' => $week['present_days'],
                                'absent' => $week['absent_days'],
                                'missing' => $week['missing_punch_days'],
                            ]) }}
                        </td>
                        <td class="px-2 py-1.5 text-right">{{ $week['late_minutes'] ? Report::formatMinutes($week['late_minutes']) : '' }}</td>
                        <td class="px-2 py-1.5 text-right">{{ $week['undertime_minutes'] ? Report::formatMinutes($week['undertime_minutes']) : '' }}</td>
                        <td colspan="{{ $canManage ? 2 : 1 }}"></td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>

<dl class="mt-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
    <x-stat-card :label="__('Days present')" :value="$totals['present_days']" />
    <x-stat-card :label="__('Absences (days)')" :value="$totals['absent_days']" />
    <x-stat-card :label="__('Times late')" :value="$totals['late_count']" />
    <x-stat-card :label="__('Total late')" :value="Report::formatMinutes($totals['late_minutes'])" />
    <x-stat-card :label="__('Total undertime')" :value="Report::formatMinutes($totals['undertime_minutes'])" />
    <x-stat-card :label="__('Missing punches')" :value="$totals['missing_punch_days']" />
</dl>
